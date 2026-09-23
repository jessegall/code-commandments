<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Codebase;
use JesseGall\CodeCommandments\Bridges;
use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Languages;

/**
 * The files a hook process has parsed, kept while they are unchanged, and the C# bridge it reads them
 * with, kept running. A one-shot hook reads cold, as ever; the journal's hook service holds one of
 * these for its whole life, so a per-edit check parses only the file that changed and reaches a
 * bridge that is already warm.
 */
final class Parses
{
    /**
     * @var array<string, array{string, Codebase}>  path => [stamp, its parse]
     */
    private array $trees = [];

    public function __construct(private readonly Bridges $bridges = new Bridges()) {}

    /**
     * $file parsed by its own engine — the parse already held while the file has not changed since.
     */
    public function of(string $file, Languages $languages): Codebase
    {
        clearstatcache(true, $file);
        $stamp = filemtime($file) . ':' . filesize($file);

        if (($this->trees[$file][0] ?? null) === $stamp) {
            return $this->trees[$file][1];
        }

        $parse = Language::ofFile($file)->engine()->scan($file, $languages, bridges: $this->bridges);
        $this->trees[$file] = [$stamp, $parse];

        return $parse;
    }
}
