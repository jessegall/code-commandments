<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts;

/**
 * The keywords standing in front of a class member — `public`, `static`, `readonly`, `abstract` and
 * the rest. A type rather than a `list<string>` on each member, because "is this static" and "what
 * is its visibility" are questions both a method and a field answer, and TypeScript's default (no
 * visibility keyword means public) belongs in one place.
 */
final class Modifiers
{
    private const array VISIBILITY = ['public', 'protected', 'private'];

    /**
     * Every keyword TypeScript allows in front of a member — what the parser consumes as a modifier
     * rather than mistaking for the member's own name.
     */
    public const array KEYWORDS = [
        'public', 'protected', 'private', 'static', 'readonly', 'abstract', 'override', 'declare', 'async',
    ];

    /**
     * @param  list<string>  $keywords  as authored, in source order
     */
    public function __construct(public readonly array $keywords = []) {}

    public function has(string $keyword): bool
    {
        return in_array($keyword, $this->keywords, true);
    }

    public function isStatic(): bool
    {
        return $this->has('static');
    }

    public function isReadonly(): bool
    {
        return $this->has('readonly');
    }

    public function isAbstract(): bool
    {
        return $this->has('abstract');
    }

    public function isAsync(): bool
    {
        return $this->has('async');
    }

    /**
     * The declared visibility, or `public` when none is written — TypeScript's default, stated
     * here so a rule about what a class EXPOSES does not have to know it.
     */
    public function visibility(): string
    {
        foreach ($this->keywords as $keyword) {
            if (in_array($keyword, self::VISIBILITY, true)) {
                return $keyword;
            }
        }

        return 'public';
    }

    public function isPublic(): bool
    {
        return $this->visibility() === 'public';
    }

    public function render(): string
    {
        return $this->keywords === [] ? '' : implode(' ', $this->keywords) . ' ';
    }
}
