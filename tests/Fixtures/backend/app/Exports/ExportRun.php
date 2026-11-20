<?php

namespace Shop\Exports;

use JesseGall\CodeCommandments\Sins\Backend\UndeclaredMember;
use JesseGall\CodeCommandments\Testing\Sinful;

interface ExportWriter
{
    public function write(string $record): void;
}

final class ExportRun
{
    #[Sinful(UndeclaredMember::class)]
    public function finish(ExportWriter $writer, iterable $records): void
    {
        foreach ($records as $record) {
            $writer->write($record);
        }

        $writer->flush();
    }
}
