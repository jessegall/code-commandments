<?php

namespace Shop\Reporting;

final class ExportSettings
{
    public function __construct(
        public readonly bool $enabled,
        public readonly bool $email,
        public readonly bool $archive,
        public readonly string $format,
    ) {}
}
