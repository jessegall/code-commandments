<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

/**
 * One marked piece of a fixture as a worked example shows it — the code, the file it is in, the lines it
 * spans there, and the comment line that names where it came from.
 */
final readonly class MarkedSource
{
    /**
     * @param  string  $scenario  what a Bad and a Good must share to be one before/after — the class for PHP, else the file
     */
    public function __construct(
        public string $file,
        public string $source,
        public ?string $heading = null,
        public string $scenario = '',
        public int $firstLine = 0,
        public int $lastLine = 0,
    ) {}

    /**
     * Does this source show line $line of $file?
     */
    public function shows(string $file, int $line): bool
    {
        return $this->file === $file && $this->firstLine <= $line && $line <= $this->lastLine;
    }

    /**
     * Is this the same scenario as $other — the same class, or failing that the same file?
     */
    public function sharesScenarioWith(self $other): bool
    {
        return $this->scenarioOf() === $other->scenarioOf();
    }

    public function sharesFileWith(self $other): bool
    {
        return $this->file === $other->file;
    }

    private function scenarioOf(): string
    {
        return $this->scenario === '' ? $this->file : $this->scenario;
    }
}
