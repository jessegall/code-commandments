<?php

namespace Symfony\Component\Console\Style;

interface StyleInterface
{
    public function title(string $message): void;
    public function section(string $message): void;
    public function listing(array $elements): void;
    public function text(array|string $message): void;
    public function success(array|string $message): void;
    public function error(array|string $message): void;
    public function warning(array|string $message): void;
    public function note(array|string $message): void;
    public function caution(array|string $message): void;
    public function table(array $headers, array $rows): void;
    public function ask(string $question, ?string $default = NULL, ?callable $validator = NULL): mixed;
    public function askHidden(string $question, ?callable $validator = NULL): mixed;
    public function confirm(string $question, bool $default = true): bool;
    public function choice(string $question, array $choices, mixed $default = NULL): mixed;
    public function newLine(int $count = 1): void;
    public function progressStart(int $max = 0): void;
    public function progressAdvance(int $step = 1): void;
    public function progressFinish(): void;
}
