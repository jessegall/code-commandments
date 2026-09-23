<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use UnitEnum;

/**
 * A parsed expression as a kind and the properties that kind carries — TypeScript's and Python's alike —
 * read and walked the same way whichever language it came from. The using class holds `$kind` (its
 * language's enum case) and `$props`; a property holding one of its own kind, or a list of them, is a
 * sub-expression.
 */
trait ExpressionTree
{
    /**
     * The value of $key on this expression — resolve-or-THROW. Which properties a kind carries is
     * fixed by the kind, so asking for one it does not have is a programming error rather than an
     * absence; a property that is legitimately optional is present and null, which is why {@see has}
     * is the probe.
     */
    public function get(string $key): mixed
    {
        return array_key_exists($key, $this->props)
            ? $this->props[$key]
            : throw UnknownProperty::of($this->kind, $key, array_keys($this->props));
    }

    /**
     * Does this expression CARRY $key at all — the question to ask when the kind is not already
     * known from the branch you are in.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->props);
    }

    public function is(UnitEnum $kind): bool
    {
        return $this->kind === $kind;
    }

    /**
     * Every expression in this tree, this one first — the flat walk a query selects over, so a
     * selector reaches a call nested in an argument of another call without knowing the shape it is
     * buried in.
     *
     * @return list<self>
     */
    public function flatten(): array
    {
        $all = [$this];

        foreach ($this->subExpressions() as $child) {
            $all = [...$all, ...$child->flatten()];
        }

        return $all;
    }

    /**
     * The expressions this one holds directly, in the order its properties list them.
     *
     * @return list<self>
     */
    public function subExpressions(): array
    {
        $children = [];

        foreach ($this->props as $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item instanceof self) {
                    $children[] = $item;
                }
            }
        }

        return $children;
    }
}
