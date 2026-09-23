<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts\Node;

/**
 * A `break` or a `continue`, and the label it jumps to when it names one.
 */
final class JumpStmt extends Stmt
{
    public function __construct(
        public readonly string $keyword,
        public readonly ?string $label = null,
    ) {}

    public function variant(): string
    {
        return trim("{$this->keyword} {$this->label}");
    }

    public function render(): string
    {
        return $this->label === null ? "{$this->keyword};" : "{$this->keyword} {$this->label};";
    }
}
