<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts\Node;

use JesseGall\CodeCommandments\Ts\Expr\Expr;

/**
 * A `switch (subject) { case … }` — the subject it dispatches on and its {@see cases}.
 */
final class SwitchStmt extends Stmt
{
    /**
     * @param  list<SwitchCase>  $cases
     */
    public function __construct(
        public readonly Expr $subject,
        public readonly array $cases = [],
    ) {}

    public function expressions(): array
    {
        return [$this->subject];
    }

    public function children(): array
    {
        return $this->cases;
    }

    public function isBranchingConstruct(): bool
    {
        return true;
    }

    /**
     * Does it handle the values it does not name — a `default:` arm? Its absence is what makes a
     * switch over a closed set silently fall through.
     */
    public function hasDefault(): bool
    {
        foreach ($this->cases as $case) {
            if ($case->isDefault()) {
                return true;
            }
        }

        return false;
    }

    public function render(): string
    {
        $cases = implode("\n", array_map(static fn (SwitchCase $c): string => '    ' . $c->render(), $this->cases));

        return "switch ({$this->subject->source()}) {\n{$cases}\n}";
    }
}
