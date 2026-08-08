<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts\Node;

/**
 * A `catch (error) { … }` — the name it binds the failure to (null for a bare `catch {`), and what
 * it does with it.
 */
final class CatchClause extends Stmt
{
    public function __construct(
        public readonly ?string $parameter,
        public readonly BlockStmt $body,
    ) {}

    public function children(): array
    {
        return [$this->body];
    }

    public function declaredNames(): array
    {
        return $this->parameter !== null ? [$this->parameter] : [];
    }

    /**
     * Does this catch SWALLOW the failure — an empty body, so the error is caught and nothing is
     * done about it? The frontend twin of the backend's `isSwallowedCatch`.
     */
    public function isSwallowedCatch(): bool
    {
        return $this->body->body === [];
    }

    public function render(): string
    {
        $parameter = $this->parameter !== null ? " ({$this->parameter})" : '';

        return "catch{$parameter} " . $this->body->render();
    }
}
