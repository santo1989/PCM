<?php

namespace Tests\Unit;

use App\Support\BackupVault;
use Tests\TestCase;

class BackupVaultTest extends TestCase
{
    private function roundTrip($data, $tamper = false)
    {
        config(['backup.keys' => BackupVault::generate('t1'), 'backup.active_key' => 't1', 'backup.chunk_bytes' => 16]);
        $in = fopen('php://memory', 'w+');
        fwrite($in, $data);
        rewind($in);
        $enc = fopen('php://memory', 'w+');
        BackupVault::encrypt($in, $enc);
        rewind($enc);
        $blob = stream_get_contents($enc);
        if ($tamper) {
            $blob[strlen($blob) - 20] = chr(ord($blob[strlen($blob) - 20]) ^ 1);
        }
        $src = fopen('php://memory', 'w+');
        fwrite($src, $blob);
        rewind($src);
        $plain = '';
        BackupVault::decrypt($src, function ($p) use (&$plain) {
            $plain .= $p;
        });

        return $plain;
    }

    public function test_encrypt_decrypt_round_trip_across_chunks()
    {
        $data = str_repeat('INSERT INTO t VALUES (1);', 20);
        $this->assertSame($data, $this->roundTrip($data));
    }

    public function test_tampered_backup_is_rejected()
    {
        $this->expectException(\RuntimeException::class);
        $this->roundTrip(str_repeat('x', 100), true);
    }

    public function test_retention_keeps_newest_one_per_day_and_one_per_month()
    {
        $now = strtotime('2026-10-25 12:00:00');
        $mk = function ($d) {
            return ['path' => $d, 'time' => strtotime($d)];
        };
        $files = [$mk('2026-10-25 02:00'), $mk('2026-10-25 01:00'), $mk('2026-10-24 02:00'), $mk('2026-08-10 02:00'), $mk('2026-08-09 02:00'), $mk('2024-01-01 02:00')];
        $keep = BackupVault::keep($files, 1, 3, 6, $now);

        $this->assertContains('2026-10-25 02:00', $keep);
        $this->assertContains('2026-10-24 02:00', $keep);   // one per day inside 3 days
        $this->assertNotContains('2026-10-25 01:00', $keep); // second backup of the same day
        $this->assertContains('2026-08-10 02:00', $keep);   // one per month inside 6 months
        $this->assertNotContains('2026-08-09 02:00', $keep);
        $this->assertNotContains('2024-01-01 02:00', $keep); // too old
    }
}
