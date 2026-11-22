<?php

namespace Symfony\Component\Console\Style;

class SymfonyStyle extends \Symfony\Component\Console\Style\OutputStyle
{
    public const MAX_LINE_LENGTH = 120;
    private \Symfony\Component\Console\Helper\SymfonyQuestionHelper $questionHelper;
    private \Symfony\Component\Console\Helper\ProgressBar $progressBar;
    private int $lineLength;
    private \Symfony\Component\Console\Output\TrimmedBufferOutput $bufferedOutput;
    private \Symfony\Component\Console\Input\InputInterface $input;
    private \Symfony\Component\Console\Output\OutputInterface $output;
    public function __construct(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output) {}
    public function block(array|string $messages, ?string $type = NULL, ?string $style = NULL, string $prefix = ' ', bool $padding = false, bool $escape = true): void {}
    public function title(string $message): void {}
    public function section(string $message): void {}
    public function listing(array $elements): void {}
    public function text(array|string $message): void {}
    public function comment(array|string $message): void {}
    public function success(array|string $message): void {}
    public function error(array|string $message): void {}
    public function warning(array|string $message): void {}
    public function note(array|string $message): void {}
    public function info(array|string $message): void {}
    public function caution(array|string $message): void {}
    public function table(array $headers, array $rows): void {}
    public function horizontalTable(array $headers, array $rows): void {}
    public function definitionList(\Symfony\Component\Console\Helper\TableSeparator|array|string ...$list): void {}
    public function ask(string $question, ?string $default = NULL, ?callable $validator = NULL): mixed {}
    public function askHidden(string $question, ?callable $validator = NULL): mixed {}
    public function confirm(string $question, bool $default = true): bool {}
    public function choice(string $question, array $choices, mixed $default = NULL, bool $multiSelect = false): mixed {}
    public function progressStart(int $max = 0): void {}
    public function progressAdvance(int $step = 1): void {}
    public function progressFinish(): void {}
    public function createProgressBar(int $max = 0): \Symfony\Component\Console\Helper\ProgressBar {}
    public function progressIterate(iterable $iterable, ?int $max = NULL): iterable {}
    public function askQuestion(\Symfony\Component\Console\Question\Question $question): mixed {}
    public function writeln(\Traversable|array|string $messages, int $type = self::OUTPUT_NORMAL): void {}
    public function write(\Traversable|array|string $messages, bool $newline = false, int $type = self::OUTPUT_NORMAL): void {}
    public function newLine(int $count = 1): void {}
    public function getErrorStyle(): \Symfony\Component\Console\Style\SymfonyStyle {}
    public function createTable(): \Symfony\Component\Console\Helper\Table {}
    private function getProgressBar(): \Symfony\Component\Console\Helper\ProgressBar {}
    public function tree(iterable $nodes, string $root = ''): void {}
    public function createTree(iterable $nodes, string $root = ''): \Symfony\Component\Console\Helper\TreeHelper {}
    private function autoPrependBlock(): void {}
    private function autoPrependText(): void {}
    private function writeBuffer(string $message, bool $newLine, int $type): void {}
    private function createBlock(iterable $messages, ?string $type = NULL, ?string $style = NULL, string $prefix = ' ', bool $padding = false, bool $escape = false): array {}
}
