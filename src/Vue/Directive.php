<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use InvalidArgumentException;

/**
 * The built-in Vue directive names, so detectors name them as `Directive::If`
 * instead of the stringly-typed `'v-if'`. The directive methods on {@see Element}
 * and {@see Query} take `string|Directive`, so either reads.
 */
enum Directive: string
{
    case If = 'v-if';
    case ElseIf = 'v-else-if';
    case Else = 'v-else';
    case For = 'v-for';
    case Show = 'v-show';
    case Model = 'v-model';
    case Html = 'v-html';
    case Text = 'v-text';
    case Pre = 'v-pre';
    case Once = 'v-once';
    case Cloak = 'v-cloak';

    /**
     * The directives that shape a node's STRUCTURE — a conditional branch (`v-if` /
     * `v-else-if` / `v-else`) or a loop (`v-for`). When a chunk carrying one is lifted
     * into a component, the directive must travel to the call site (the branch/list
     * stays where the chunk was), not into the component.
     *
     * @return list<self>
     */
    public static function structural(): array
    {
        return [self::If, self::ElseIf, self::Else, self::For];
    }

    /**
     * Normalise a `string|Directive` to a directive attribute name — throwing if a
     * string isn't a known directive, so a typo'd `'v-fi'` fails loud instead of
     * silently matching nothing.
     */
    public static function name(string|self $directive): string
    {
        if ($directive instanceof self) {
            return $directive->value;
        }

        return (self::tryFrom($directive) ?? throw new InvalidArgumentException("Unknown Vue directive: {$directive}"))->value;
    }
}
