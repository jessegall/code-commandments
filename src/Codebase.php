<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The root type both parse engines share: a parsed body of code to judge — PHP
 * files ({@see Ast\Codebase}) or Vue components ({@see Vue\Codebase}). Each exposes
 * its own fluent selectors (the languages differ), but everything that doesn't parse
 * or detect — the runner, the fixture verifiers, the canon — names a codebase by
 * this base type, so it never has to know which engine it is holding.
 */
interface Codebase
{
}
