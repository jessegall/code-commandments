<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag `Option` inside a union or nullable type — `Option | string`,
 * `Option | null`, `?Option`. Option already encodes value-or-nothing, so
 * unioning it with a raw type or null is a contradiction: `Option | null` is
 * two absence encodings stacked, and `Option | string` is "either wrapped or
 * not — pick one". The alternatives belong INSIDE the generic (`Option<string>`),
 * never beside it.
 *
 *
 *
 * @method-generated-start
 * @method static optionClass(string $value)
 * @method-generated-end
 */
#[IntroducedIn('1.80.0')]
class NoOptionInUnionProphet extends PhpCommandment
{
    private const DEFAULT_OPTION_CLASS = 'App\\Support\\Option';

    public function description(): string
    {
        return 'Do not union Option with other types or null — Option is the whole type';
    }

    /**
     * Never flag the configured Option primitive itself.
     *
     * @return list<class-string>
     */
    public function exemptClasses(): array
    {
        $class = ltrim((string) $this->config('option_class', self::DEFAULT_OPTION_CLASS), '\\');

        return $class === '' ? [] : [$class];
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A type declaration unions `Option` with another type or null '
                . '(`Option | string`, `Option | null`, `?Option`) — Option already '
                . 'models value-or-nothing, so combining it with a raw type or null '
                . 'is a contradiction.'
            )
            ->leaveWhen(
                'Never. Two absence encodings (`Option | null`) collapse to one; a '
                . 'wrapped-or-raw union (`Option | string`) should pick a side.'
            )
            ->whenUnsure(
                'Make it a bare `Option` and move the alternatives into its generic '
                . '(`Option<string>`). If a plain nullable is what you actually want, '
                . 'drop the Option and use `?T`.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
`Option` IS the value-or-nothing type. Putting it in a union undoes that:

  - `Option | null` / `?Option` — two absence encodings stacked. A caller would
    have to check `=== null` AND `isEmpty()`. Which "nothing" is it?
  - `Option | string` — "either wrapped or a bare string". The caller can't
    rely on the Option contract because half the time it isn't one.

The variants belong INSIDE the Option, not beside it.

Bad:
    Option | string | null $elementType = null,
    Option | array | string | null $isVisibleRule = null,
    public function find(): Option | null { … }

Good — KEEP the Option; move the inner shape into the generic:
    /** @var Option<string> */
    Option $elementType,

    /** @return Option<array|string> */
    public function rule(): Option { … }

DO NOT "fix" this by deleting the Option and widening to a raw union —
`Option | array | string | null`  →  `array | string | null` is BACKWARDS
(now it's an un-modelled value-or-nothing AND a fat union). The answer is
`Option<array|string>`: the `null` becomes the Option's absence, the rest its
generic. (A plain `?Thing` is only right when you never wanted an Option here.)

WHAT FIRES — `Option` appearing in a PHP union type (`Option | …`) or a nullable
type (`?Option`), in a parameter, return, or property type.

WHAT DOES NOT — a bare `Option` type, or an `Option<...>` generic in a docblock
(the inner union belongs there).

Configure via:

    Backend\NoOptionInUnionProphet::class => [
        'option_class' => App\Support\Option::class,
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $optionShort = $this->optionShort();
        $warnings = [];

        foreach ($finder->findInstanceOf($ast, Node\UnionType::class) as $union) {
            foreach ($union->types as $type) {
                if ($this->isOption($type, $optionShort)) {
                    $warnings[] = $this->warn($union->getStartLine(), $content);

                    break;
                }
            }
        }

        foreach ($finder->findInstanceOf($ast, Node\NullableType::class) as $nullable) {
            if ($this->isOption($nullable->type, $optionShort)) {
                $warnings[] = $this->warn($nullable->getStartLine(), $content);
            }
        }

        $flaggedLines = [];

        foreach ($warnings as $warning) {
            if ($warning->line !== null) {
                $flaggedLines[$warning->line] = true;
            }
        }

        // Docblock pass — the same contradiction in `@param`/`@return`/`@var`
        // (`Option<string>|null`, `?Option`), which the native-type pass cannot
        // see when the PHP type is a bare `Option` but the docblock unions it.
        foreach (explode("\n", $content) as $index => $text) {
            $line = $index + 1;

            if (isset($flaggedLines[$line])) {
                continue;
            }

            if (preg_match('/@(?:param|return|var)\s+(.+)$/', $text, $m) && $this->docTypeUnionsOption($this->cleanDocType($m[1]), $optionShort)) {
                $warnings[] = $this->warn($line, $content);
            }
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * Whether a docblock type string unions/nullables `Option` at the TOP level,
     * after stripping generics — so `Option<string>|null` and `?Option` fire,
     * but `Option<string|int>` (the union lives inside the generic) does not.
     */
    /**
     * The type portion of a docblock tag value: drop the variable name and any
     * trailing description, and strip whitespace (so a space inside a generic —
     * `Option<array<string, int>>` — does not truncate the type).
     */
    private function cleanDocType(string $rest): string
    {
        $type = preg_replace('/\$\w+.*$/', '', $rest) ?? $rest;

        return preg_replace('/\s+/', '', $type) ?? $type;
    }

    private function docTypeUnionsOption(string $type, string $optionShort): bool
    {
        $stripped = $type;

        do {
            $previous = $stripped;
            $stripped = preg_replace('/<[^<>]*>/', '', $stripped) ?? $stripped;
        } while ($stripped !== $previous);

        $nullable = str_starts_with($stripped, '?');
        $atoms = array_values(array_filter(array_map('trim', explode('|', ltrim($stripped, '?')))));

        $hasOption = false;

        foreach ($atoms as $atom) {
            $parts = explode('\\', ltrim($atom, '\\'));

            if ((end($parts) ?: $atom) === $optionShort) {
                $hasOption = true;
            }
        }

        return $hasOption && (count($atoms) > 1 || $nullable);
    }

    private function warn(int $line, string $content): \JesseGall\CodeCommandments\Results\Warning
    {
        return $this->warningAt(
            $line,
            'A type unions `Option` with another type or null — Option already encodes value-or-nothing, so `Option | …` / `?Option` is a contradiction. The fix is a BARE `Option` with the alternatives moved INSIDE its generic — `Option<array|string>`. Do NOT "fix" it by deleting the Option and falling back to a raw `array|string|null` union: that is the wrong direction. Option is the right model for value-or-nothing — keep it, just stop unioning it.',
            $this->lineSnippet($content, $line),
            'option-in-union',
        );
    }

    private function isOption(Node $type, string $optionShort): bool
    {
        return $type instanceof Node\Name && $type->getLast() === $optionShort;
    }

    private function optionShort(): string
    {
        $class = (string) $this->config('option_class', self::DEFAULT_OPTION_CLASS);
        $parts = explode('\\', ltrim($class, '\\'));

        return end($parts) ?: 'Option';
    }

}
