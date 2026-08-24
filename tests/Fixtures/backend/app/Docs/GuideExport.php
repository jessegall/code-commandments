<?php

namespace Shop\Docs;

use JesseGall\CodeCommandments\Sins\Backend\DivergentTwin;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Labels\SpoolArchive;

/**
 * Writes a rendered guide to the docs directory, which the help viewer serves straight off disk. It
 * does the same job as the spool archive and writes straight over the top, so a viewer reading during
 * an export is served half a page.
 */
final class GuideExport
{
    public function __construct(private readonly SpoolArchive $archive) {}

    #[Sinful(DivergentTwin::class)]
    public function write(string $path, string $guide): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }

        file_put_contents($path, $guide);
    }

    /**
     * The same export, routed through the one place that knows how a file is written here — so the
     * next improvement to that write is this method's too, without anyone remembering it exists.
     */
    #[Fixed(DivergentTwin::class)]
    public function export(string $path, string $guide): void
    {
        $this->archive->store($path, $guide);
    }
}
