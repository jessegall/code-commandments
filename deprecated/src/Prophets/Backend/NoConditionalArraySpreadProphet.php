<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag the "spread a ternary whose other arm is an empty array" idiom used to
 * conditionally include keys in an array literal.
 */
#[IntroducedIn('1.57.0')]
class NoConditionalArraySpreadProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Assemble conditional array shapes with a builder, not a spread of a ternary with an empty arm';
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A `...` spread element inside an array literal spreads a ternary '
                . 'whose other arm is an empty array (`[]` / `T_Array::empty()`) — '
                . 'i.e. the spread exists only to conditionally include a key or two.'
            )
            ->leaveWhen(
                'The spread merges a genuinely variable-length array that happens '
                . 'to fall back to empty (e.g. `...($extra ?? [])` of an unknown '
                . 'set), not a hand-listed key or two gated by a condition.'
            )
            ->whenUnsure(
                'If you can name the key being conditionally added, it is the '
                . 'idiom — assemble it with `T_Array::from(...)->putUnless…()`. If '
                . 'the spread is an opaque variable array, leave it.'
            );
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Conditionally including a key by spreading a ternary whose other arm is an
empty array is a dirty idiom:

    return [
        'name' => $port->name,
        ...($port->label === null ? T_Array::empty() : ['label' => $port->label]),
        ...($hasOptions ? ['options' => $port->options] : T_Array::empty()),
    ];

Three things make it hard to read:

  1. The empty-array arm is pure noise — it exists only to spread nothing.
  2. The ternary direction flips (`cond ? [k] : empty` vs `empty? empty :
     [k]`), so you cannot scan which keys are conditional.
  3. Conditional and always-present keys are interleaved, hiding the array's
     real shape.

Assemble it with a builder instead — the always-present keys are one clean
literal, each conditional key is one named guard:

    return T_Array::from([
            'name' => $port->name,
        ])
        ->putUnlessNull('label', $port->label)
        ->putWhen($hasOptions, 'options', $port->options)
        ->toArray();

(`putUnlessEmpty` guards on null/`[]`/`''`; `putUnlessNull` on null only;
`putWhen` on an explicit boolean.) Building the array imperatively with
`$out['label'] = …` instead would just trade this for array-bag indexing —
the builder is the clean home for a conditional array shape.

WHAT FIRES — a `...` (spread) element inside an array literal whose operand
is a ternary (full `a ? b : c` or short `a ?: c`) and at least one arm is an
empty array: a `[]` literal, `T_Array::empty()`, or `T_Array::EMPTY`.

WHAT DOES NOT — a spread of a plain variable or a `?? []` null-fallback
(`...($extra ?? [])`): that merges an opaque, variable-length array, not a
hand-listed key gated by a condition.

This is advisory and not auto-fixed: the base array, the guard choice, and
the key name are semantic, and moving keys onto the builder shifts their
emitted order (irrelevant for most payloads, but yours to confirm).
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $warnings = [];
        $seen = [];

        /** @var array<Expr\Array_> $arrays */
        $arrays = $finder->findInstanceOf($ast, Expr\Array_::class);

        foreach ($arrays as $array) {
            foreach ($array->items as $item) {
                if (! $item instanceof Node\ArrayItem || ! $item->unpack) {
                    continue;
                }

                if (! $this->isConditionalEmptySpread($item->value)) {
                    continue;
                }

                $line = $item->getStartLine();

                if (isset($seen[$line])) {
                    continue;
                }

                $seen[$line] = true;
                $warnings[] = $this->warningAt(
                    $line,
                    'Conditional spread to optionally include array key(s) — assemble with '
                    . '`T_Array::from([...])->putUnlessNull()/putUnlessEmpty()/putWhen()->toArray()` '
                    . 'instead of spreading a ternary with an empty-array arm.',
                    null,
                    'conditional-array-spread',
                );
            }
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * A ternary (full or short) where at least one arm is an empty array.
     */
    private function isConditionalEmptySpread(Expr $value): bool
    {
        if (! $value instanceof Expr\Ternary) {
            return false;
        }

        // Short ternary `$cond ?: []` has a null `if` branch.
        if ($value->if !== null && $this->isEmptyArrayish($value->if)) {
            return true;
        }

        return $this->isEmptyArrayish($value->else);
    }

    /**
     * `[]`, `T_Array::empty()`, or `T_Array::EMPTY`.
     */
    private function isEmptyArrayish(Expr $expr): bool
    {
        if ($expr instanceof Expr\Array_) {
            return $expr->items === [];
        }

        if ($expr instanceof Expr\StaticCall
            && $expr->class instanceof Node\Name
            && $expr->name instanceof Node\Identifier
        ) {
            return $expr->class->getLast() === 'T_Array'
                && strtolower($expr->name->toString()) === 'empty';
        }

        if ($expr instanceof Expr\ClassConstFetch
            && $expr->class instanceof Node\Name
            && $expr->name instanceof Node\Identifier
        ) {
            return $expr->class->getLast() === 'T_Array'
                && $expr->name->toString() === 'EMPTY';
        }

        return false;
    }
}
