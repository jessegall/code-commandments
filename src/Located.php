<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * Anything a {@see Finding} can point at — it knows its `file`, its `file:line`
 * {@see location}, and a short {@see scope} for the report. The one thing the runner
 * needs of a frontend detector's result, so it reduces any of them to a `Finding` the
 * same way: an {@see Vue\ElementMatch} over the template, or a
 * {@see Vue\TypeDeclarationMatch} over declaration space, are both {@see Located}.
 */
interface Located
{
    public function file(): string;

    public function location(): string;

    public function scope(): string;
}
