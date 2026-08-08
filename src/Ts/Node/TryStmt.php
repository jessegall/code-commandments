<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts\Node;

/**
 * A `try { … } catch (e) { … } finally { … }`.
 */
final class TryStmt extends Stmt
{
    public function __construct(
        public readonly BlockStmt $body,
        public readonly ?CatchClause $catch = null,
        public readonly ?BlockStmt $finally = null,
    ) {}

    public function children(): array
    {
        return array_values(array_filter([$this->body, $this->catch, $this->finally]));
    }

    public function isBranchingConstruct(): bool
    {
        return true;
    }

    public function render(): string
    {
        $catch = $this->catch !== null ? ' ' . $this->catch->render() : '';
        $finally = $this->finally !== null ? ' finally ' . $this->finally->render() : '';

        return 'try ' . $this->body->render() . $catch . $finally;
    }
}
