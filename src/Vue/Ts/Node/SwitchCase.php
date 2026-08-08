<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

use JesseGall\CodeCommandments\Vue\Expr\Expr;

/**
 * One arm of a {@see SwitchStmt} — `case value:` with the statements that follow it, or `default:`
 * when it names no value.
 */
final class SwitchCase extends Stmt
{
    /**
     * @param  ?Expr  $test  the matched value, null for `default:`
     * @param  list<Node>  $body
     */
    public function __construct(
        public readonly ?Expr $test,
        public readonly array $body = [],
    ) {}

    public function isDefault(): bool
    {
        return $this->test === null;
    }

    public function expressions(): array
    {
        return $this->test !== null ? [$this->test] : [];
    }

    public function children(): array
    {
        return $this->body;
    }

    public function render(): string
    {
        $label = $this->test !== null ? 'case ' . $this->test->source() . ':' : 'default:';
        $body = implode("\n", array_map(static fn (Node $n): string => '        ' . $n->render(), $this->body));

        return $this->body === [] ? $label : "{$label}\n{$body}";
    }
}
