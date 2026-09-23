<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts\Node;

/**
 * `label: statement` — a statement given a name a `break` or `continue` can jump to.
 */
final class LabelledStmt extends Stmt
{
    public function __construct(
        public readonly string $label,
        public readonly Node $body,
    ) {}

    public function children(): array
    {
        return [$this->body];
    }

    public function variant(): string
    {
        return $this->label;
    }

    public function render(): string
    {
        return "{$this->label}: " . $this->body->render();
    }
}
