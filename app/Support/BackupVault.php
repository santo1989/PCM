<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Backup helpers shared by the db:backup / backup:* commands: AES-256-GCM streaming crypto, the key ring and retention.
 * File layout (same "IVB1" format as Dynamic_Inventory, so backup:decrypt reads both):
 *   "IVB1" | keyIdLen(1) | keyId | baseNonce(8) | { len(4, BE) | ciphertext | tag(16) }*
 * Chunk i uses nonce baseNonce|i. The header and a final-chunk flag are authenticated (AAD), so a flipped byte, a
 * re-ordered/dropped chunk or a truncated file fails verification instead of silently restoring bad data.
 * Kept PHP 7.3 compatible: most apps still run Laravel 8 on ^7.3.
 */
class BackupVault
{
    const MAGIC = 'IVB1';

    public static function dir()
    {
        return storage_path('app/backups');
    }

    /** @return array id => raw 32-byte key */
    public static function keys()
    {
        $keys = [];
        foreach (array_filter(array_map('trim', explode(',', (string) config('backup.keys')))) as $pair) {
            list($id, $b64) = array_pad(explode(':', $pair, 2), 2, '');
            $raw = base64_decode($b64, true);
            if (preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) && $raw !== false && strlen($raw) === 32) {
                $keys[$id] = $raw;
            }
        }

        return $keys;
    }

    /** @return array [id, key] used for NEW backups, or null when encryption is not configured */
    public static function active()
    {
        $keys = self::keys();
        $id = (string) config('backup.active_key');

        return isset($keys[$id]) ? [$id, $keys[$id]] : null;
    }

    public static function generate($id)
    {
        return $id . ':' . base64_encode(random_bytes(32));
    }

    /** @param resource $in @param resource $out @return string key id used */
    public static function encrypt($in, $out)
    {
        $active = self::active();
        if (!$active) {
            throw new RuntimeException('No active backup key (BACKUP_KEYS / BACKUP_ACTIVE_KEY). Run `php artisan backup:keygen`.');
        }
        list($keyId, $key) = $active;
        $base = random_bytes(8);
        $header = self::MAGIC . chr(strlen($keyId)) . $keyId . $base;
        fwrite($out, $header);

        $size = (int) config('backup.chunk_bytes');
        $i = 0;
        $current = self::read($in, $size);
        while (true) {
            $next = self::read($in, $size);
            $final = $next === '';
            $tag = '';
            $cipher = openssl_encrypt($current, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $base . pack('N', $i), $tag, $header . ($final ? 'F' : 'M'), 16);
            if ($cipher === false) {
                throw new RuntimeException('Encryption failed.');
            }
            fwrite($out, pack('N', strlen($cipher)) . $cipher . $tag);
            if ($final) {
                return $keyId;
            }
            $current = $next;
            $i++;
        }
    }

    /**
     * Decrypts and authenticates every chunk, handing plaintext to $sink.
     *
     * @param resource $in
     * @throws RuntimeException on tampering, wrong key or truncation
     */
    public static function decrypt($in, callable $sink)
    {
        $magic = fread($in, 4);
        if ($magic !== self::MAGIC) {
            throw new RuntimeException('Not an encrypted backup file.');
        }
        $idLen = ord((string) fread($in, 1));
        $keyId = (string) fread($in, $idLen);
        $base = (string) fread($in, 8);
        if (strlen($base) !== 8) {
            throw new RuntimeException('Backup file is truncated.');
        }
        $keys = self::keys();
        if (!isset($keys[$keyId])) {
            throw new RuntimeException("The encryption key '{$keyId}' for this backup is not in BACKUP_KEYS.");
        }
        $header = $magic . chr($idLen) . $keyId . $base;

        $i = 0;
        $frame = self::frame($in);
        if ($frame === null) {
            throw new RuntimeException('Backup file is truncated (no data).');
        }
        while (true) {
            $next = self::frame($in); // one frame of look-ahead tells us which chunk is the last, without seeking
            $final = $next === null;
            $plain = openssl_decrypt($frame[0], 'aes-256-gcm', $keys[$keyId], OPENSSL_RAW_DATA, $base . pack('N', $i), $frame[1], $header . ($final ? 'F' : 'M'));
            if ($plain === false) {
                throw new RuntimeException('Backup failed authentication (corrupt, tampered or wrong key).');
            }
            $sink($plain);
            if ($final) {
                return $keyId;
            }
            $frame = $next;
            $i++;
        }
    }

    /** @return array [path, time] newest first, for this database's dumps (.sql / .sql.enc) */
    public static function files($database)
    {
        $out = [];
        foreach (array_merge(glob(self::dir() . '/' . $database . '_*.sql') ?: [], glob(self::dir() . '/' . $database . '_*.sql.enc') ?: []) as $path) {
            $out[] = ['path' => $path, 'time' => filemtime($path)];
        }
        usort($out, function ($a, $b) {
            return [$b['time'], $b['path']] <=> [$a['time'], $a['path']];
        });

        return $out;
    }

    /**
     * "Keep the last N, one per day for X days, one per month for Y months." Pure function of (files, policy, now).
     *
     * @param array $files newest first, as returned by files()
     * @return array paths to KEEP; everything else is prunable
     */
    public static function keep(array $files, $keepLast, $dailyDays, $monthly, $now)
    {
        $keep = [];
        $days = [];
        $months = [];
        $dayCut = strtotime(date('Y-m-d', $now - $dailyDays * 86400));
        $monthCut = strtotime(date('Y-m-01', strtotime("-{$monthly} months", $now)));
        foreach ($files as $n => $f) {
            $day = date('Y-m-d', $f['time']);
            $month = date('Y-m', $f['time']);
            if ($n < $keepLast
                || ($f['time'] >= $dayCut && !isset($days[$day]))
                || ($f['time'] >= $monthCut && !isset($months[$month]))) {
                $keep[] = $f['path'];
            }
            if ($f['time'] >= $dayCut) {
                $days[$day] = true;
            }
            if ($f['time'] >= $monthCut) {
                $months[$month] = true;
            }
        }

        return $keep;
    }

    /** Runs the mysql client against the default connection; the password goes via the environment, never argv. */
    public static function mysql(array $c, array $args, $stdin = null)
    {
        $p = new Process(array_merge([config('backup.mysql'), '-h', $c['host'], '-P', (string) $c['port'], '-u', $c['username']], $args),
            null, ['MYSQL_PWD' => (string) $c['password']], $stdin, 600);
        $p->run();

        return $p;
    }

    private static function read($in, $size)
    {
        $buf = '';
        while (strlen($buf) < $size && !feof($in)) {
            $part = fread($in, $size - strlen($buf));
            if ($part === false || $part === '') {
                break;
            }
            $buf .= $part;
        }

        return $buf;
    }

    /** @return array|null [ciphertext, tag]; null at a clean end of file */
    private static function frame($in)
    {
        $lenRaw = self::read($in, 4);
        if ($lenRaw === '') {
            return null;
        }
        if (strlen($lenRaw) !== 4) {
            throw new RuntimeException('Backup file is truncated.');
        }
        $len = unpack('N', $lenRaw)[1];
        if ($len > (int) config('backup.chunk_bytes') + 64) {
            throw new RuntimeException('Backup file is corrupt.');
        }
        $cipher = self::read($in, $len);
        $tag = self::read($in, 16);
        if (strlen($cipher) !== $len || strlen($tag) !== 16) {
            throw new RuntimeException('Backup file is truncated.');
        }

        return [$cipher, $tag];
    }
}
