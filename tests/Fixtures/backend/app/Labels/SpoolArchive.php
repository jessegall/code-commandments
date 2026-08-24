<?php

namespace Shop\Labels;

use JesseGall\CodeCommandments\Sins\Backend\DivergentTwin;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Keeps a copy of every spool run, so a reprint can be proved against what was actually sent. The
 * printer daemon reads this directory while the app writes it, which is why the write is done beside
 * the file and moved into place: a reader sees one whole version or the other, never half of either.
 */
final class SpoolArchive
{
    #[Sinful(DivergentTwin::class)]
    public function store(string $path, string $spool): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }

        $partial = $path . '.' . getmypid();

        file_put_contents($partial, $spool);

        rename($partial, $path);
    }
}
