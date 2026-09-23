<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detector as BaseDetector;
use JesseGall\CodeCommandments\Located;

/**
 * A detector over C# — its findings read off the {@see Codebase} the Roslyn bridge reads, through the
 * same fluent query the other engines answer.
 */
interface Detector extends BaseDetector
{
    /**
     * @return list<Located>
     */
    public function find(Codebase $codebase): array;
}
