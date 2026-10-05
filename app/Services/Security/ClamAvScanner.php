<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * ClamAV scanner (spec §7): shells out to clamscan on the
 * stored temp file. Exit 0 = clean, 1 = infected, anything
 * else is a scanner failure (fail closed: reject the upload).
 */
final class ClamAvScanner implements MalwareScannerInterface
{
    public function isClean(string $path): bool
    {
        $exitCode = Process::run(['clamscan', '--no-summary', $path])
            ->exitCode();

        if ($exitCode === 1) {
            return false;
        }

        if ($exitCode !== 0) {
            throw new RuntimeException(
                'Malware scan failed (clamscan exit '.$exitCode.').',
            );
        }

        return true;
    }

    public function driver(): string
    {
        return 'clamav';
    }
}
