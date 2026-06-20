<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\Archetype;
use JesseGall\CodeCommandments\Support\RoleInference;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag a HAND-ROLLED ENUM — the pre-8.1 idiom of a class that emulates an
 * enumeration with a private/protected constructor and a closed set of
 * parameterless static "case" factories — and nudge it to a native `enum`.
 *
 * Detection is STRUCTURAL via {@see RoleInference} ({@see Archetype::ManualEnum}),
 * never a name/suffix: a non-public constructor (instances are not freely
 * constructible) plus >= 2 parameterless public static methods that each build and
 * return an instance of the class. That is the shape a native `enum` exists to
 * replace — so it fires on the UNMARKED ones too, regardless of what they are
 * called.
 */
#[IntroducedIn('2.8.0')]
class PreferNativeEnumProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Prefer a native enum over a hand-rolled constant class';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A class is a hand-rolled enum — a private/protected constructor plus '
                . 'two or more PARAMETERLESS public static "case" factories that each '
                . 'return a fixed instance of the class. This is the pre-8.1 idiom a '
                . 'native `enum` replaces.'
            )
            ->leaveWhen(
                'The instances are not a CLOSED set — there is also a parameterised '
                . 'factory (`from(int $x)`) producing open values, so it is a value '
                . 'object with a few named constants, not an enumeration. Or the project '
                . 'targets PHP < 8.1, where native enums are unavailable.'
            )
            ->whenUnsure(
                'If the static instances enumerate a fixed, closed set of values, make '
                . 'the class a native `enum` (a BACKED enum if each case wraps a scalar). '
                . 'You gain `cases()`, exhaustive `match`, identity comparison, and type '
                . 'safety, and delete the constructor + factory boilerplate.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A hand-rolled enum is a class that emulates an enumeration the way you had to
before PHP 8.1: a private constructor (so callers cannot make arbitrary
instances) and a fixed set of parameterless static factories, one per "case".

Bad — a constant class doing an enum's job by hand:
    final class Suit
    {
        private function __construct(public readonly string $value) {}

        public static function hearts(): self   { return new self('H'); }
        public static function spades(): self    { return new self('S'); }
        public static function diamonds(): self  { return new self('D'); }
    }

Good — a native backed enum:
    enum Suit: string
    {
        case Hearts = 'H';
        case Spades = 'S';
        case Diamonds = 'D';
    }

The native enum gives you `Suit::cases()`, exhaustive `match`, guaranteed
identity (`===`) per case, `Suit::from('H')` / `tryFrom`, and real type safety —
for a fraction of the code.

WHAT FIRES — a class (not already an `enum`) with a private/protected constructor
AND >= 2 parameterless public static methods that each `return new self/static(...)`.
Detected by SHAPE, not by name.

WHAT DOES NOT — a value object with a parameterised factory (`Money::fromCents(int)`)
is an open value type, not a closed set; a class with a public constructor; a
singleton (a single `getInstance()`); and any real `enum`.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name === null) {
                continue;
            }

            if (RoleInference::infer($class)->archetype() !== Archetype::ManualEnum) {
                continue;
            }

            $name = $class->name->toString();

            $warnings[] = $this->warningAt(
                $class->getStartLine(),
                sprintf(
                    '`%s` is a hand-rolled enum — a private constructor with parameterless static case factories. PHP 8.1+ native `enum` expresses this closed set with far less code, real type safety, exhaustive `match`, identity per case, and `cases()`. Convert it to an `enum` (a backed enum if each case wraps a scalar).',
                    $name,
                ),
                null,
                'manual-enum:' . $name,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }
}
