<?php

namespace Symfony\Component\Console\Style;

abstract class OutputStyle implements \Symfony\Component\Console\Output\OutputInterface, \Symfony\Component\Console\Style\StyleInterface
{
    private \Symfony\Component\Console\Output\OutputInterface $output;
    public function __construct(\Symfony\Component\Console\Output\OutputInterface $output) {}
    public function newLine(int $count = 1): void {}
    public function createProgressBar(int $max = 0): \Symfony\Component\Console\Helper\ProgressBar {}
    public function write(\Traversable|array|string $messages, bool $newline = false, int $type = self::OUTPUT_NORMAL): void {}
    public function writeln(\Traversable|array|string $messages, int $type = self::OUTPUT_NORMAL): void {}
    public function setVerbosity(int $level): void {}
    public function getVerbosity(): int {}
    public function setDecorated(bool $decorated): void {}
    public function isDecorated(): bool {}
    public function setFormatter(\Symfony\Component\Console\Formatter\OutputFormatterInterface $formatter): void {}
    public function getFormatter(): \Symfony\Component\Console\Formatter\OutputFormatterInterface {}
    public function isSilent(): bool {}
    public function isQuiet(): bool {}
    public function isVerbose(): bool {}
    public function isVeryVerbose(): bool {}
    public function isDebug(): bool {}
    protected function getErrorOutput(): \Symfony\Component\Console\Output\OutputInterface {}
}
