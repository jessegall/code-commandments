<?php

namespace Illuminate\Console;

class OutputStyle extends \Symfony\Component\Console\Style\SymfonyStyle implements \Illuminate\Console\Contracts\NewLineAware
{
    private $output = NULL;
    protected $newLinesWritten = 1;
    protected $newLineWritten = false;
    public function __construct(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output) {}
    public function askQuestion(\Symfony\Component\Console\Question\Question $question): mixed {}
    public function write(\Traversable|array|string $messages, bool $newline = false, int $options = 0): void {}
    public function writeln(\Traversable|array|string $messages, int $type = self::OUTPUT_NORMAL): void {}
    public function newLine(int $count = 1): void {}
    public function newLinesWritten() {}
    public function newLineWritten() {}
    protected function trailingNewLineCount($messages) {}
    public function isQuiet(): bool {}
    public function isVerbose(): bool {}
    public function isVeryVerbose(): bool {}
    public function isDebug(): bool {}
    /** @return \Symfony\Component\Console\Output\OutputInterface */
    public function getOutput() {}
}
