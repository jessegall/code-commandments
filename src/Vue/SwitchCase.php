<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * One branch of a {@see SwitchCaseChain} — the literal the subject is tested against, and the element
 * rendered when it matches. The `v-else` branch matches no literal, so its key is absent and it
 * answers to the default slot instead.
 */
final readonly class SwitchCase
{
    private function __construct(
        public ?string $key,
        public Element $element,
    ) {}

    /**
     * A branch that matches one literal — a `v-if`/`v-else-if` arm.
     */
    public static function matching(string $key, Element $element): self
    {
        return new self($key, $element);
    }

    /**
     * The `v-else` arm: what renders when no case matched.
     */
    public static function fallback(Element $element): self
    {
        return new self(null, $element);
    }

    public function isFallback(): bool
    {
        return $this->key === null;
    }

    /**
     * The slot this branch is written into — its own key, or `default` for the fallback.
     */
    public function slot(): string
    {
        return $this->key ?? 'default';
    }
}
