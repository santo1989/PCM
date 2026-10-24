<?php

namespace App\Console\Commands;

use App\Support\BackupVault;
use Illuminate\Console\Command;

class BackupDecrypt extends Command
{
    protected $signature = 'backup:decrypt {input : path to a .sql.enc file} {output : path for the decrypted .sql}';
    protected $description = 'Decrypt and authenticate a backup file with the key ring in this environment';

    public function handle()
    {
        $in = @fopen((string) $this->argument('input'), 'rb');
        if (!$in) {
            $this->error('Cannot read the input file.');
            return 1;
        }
        $outPath = (string) $this->argument('output');
        if (file_exists($outPath)) {
            $this->error('Refusing to overwrite an existing output file.');
            return 1;
        }
        $out = fopen($outPath, 'wb');
        try {
            $keyId = BackupVault::decrypt($in, function ($plain) use ($out) {
                fwrite($out, $plain);
            });
        } catch (\Throwable $e) {
            fclose($out);
            @unlink($outPath);
            $this->error($e->getMessage());
            return 1;
        }
        fclose($out);
        $this->info("Decrypted with key '{$keyId}' to {$outPath}");

        return 0;
    }
}
