<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * A codebase read as parsed modules — one per file, each answering {@see ParsedModule} — which is what
 * the fixture harness reads markers and examples from, whatever language the modules are in.
 */
interface ModuleCodebase extends Codebase
{
    /**
     * @return list<ParsedModule>
     */
    public function modules(): array;
}
