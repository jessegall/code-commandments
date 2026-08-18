# Promote a repeated call to a method — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### repeated-guard

The SAME compound guard condition recurs in ≥2 places — the same check spelled differently (inline reaches vs locals) or reordered still counts, so a copied condition has no name

```php
----------[ Bad ]----------

// in Shop\Domain\ReviewQueue
public function promote(array $items): array
{
    $ready = [];

    foreach ($items as $item) {
        if ($item->published && $item->approved) {
            $ready[] = $item;
        }
    }

    return $ready;
}

// in Shop\Domain\RangeFilter
public function inside($value, $bound): bool
{
    return $value >= $bound->min && $value <= $bound->max;
}

// in Shop\Domain\RangeFilter
public function clamp($value, $bound): mixed
{
    if ($value <= $bound->max && $value >= $bound->min) {
        return $value;
    }

    return $value < $bound->min ? $bound->min : $bound->max;
}

// in Shop\Domain\FrameLookup
public function get(string $node, string $port): mixed
{
    if (array_key_exists($node, $this->frames) && array_key_exists($port, $this->frames[$node])) {
        return $this->frames[$node][$port];
    }

    return null;
}

// in Shop\Domain\FrameLookup
public function has(string $node, string $port): bool
{
    return array_key_exists($node, $this->frames) && array_key_exists($port, $this->frames[$node]);
}

// in Shop\Domain\AccessPolicy
public function allow($user, $account): bool
{
    return $user->active && $account->verified;
}

// in Shop\Domain\AccessPolicy
public function audit($user, $account): string
{
    $live = $user->active;
    $ok = $account->verified;

    return $live && $ok ? 'granted' : 'denied';
}

// in Shop\Domain\PublishGate
public function visible($item): bool
{
    return $item->published && $item->approved;
}

----------[ Good ]----------

// The guard promoted to a NAME: `isLocked()` spells `$user->suspended && $account->frozen` once and
// every site asks the predicate, so the condition has one home and moves in one place.

public function review($user, $account): string
{
    return $this->isLocked($user, $account) ? 'locked' : 'open';
}
```

### repeated-named-call

The same `with`-style (variadic) method is called with the same named argument at 2+ sites, instead of a named helper on the type

```php
----------[ Bad ]----------

// in Shop\Http\Pages\RepeatedCall\PortHydrator
public function hydrate(UiNode $node, string $name): UiNode
{
    $required = in_array($name, $this->requiredPorts, true);

    return $node->copyWith(metadata: PortMeta::from(['name' => $name, 'required' => $required])->toArray());
}

// in Shop\Http\Pages\RepeatedCall\PanelHydrator
public function hydrate(UiNode $node, ?string $heading): UiNode
{
    $heading = $this->normalise($heading);

    return $node->copyWith(metadata: PanelMeta::from(['heading' => $heading])->toArray());
}

// in Shop\Http\Pages\RepeatedCall\CardHydrator
public function hydrate(UiNode $node, string $title, string $state): UiNode
{
    return $node->copyWith(metadata: CardMeta::from(['title' => $title, 'tone' => $this->tone($state)])->toArray());
}

----------[ Good ]----------

// The repeated call promoted to a method on the receiver's type: `UiNode::withMetadata()` does the
// `copyWith(metadata: $meta->toArray())`, so the call site says WHAT it does and says it once.

public function decorate(UiNode $node, CardMeta $meta): UiNode
{
    return $node->withMetadata($meta);
}
```

### repeated-type-guard

The SAME multi-`instanceof` type-narrowing guard (`$x instanceof A && $x->y instanceof B`) is written verbatim in ≥2 places — a check with no name, copied instead of named

```php
----------[ Bad ]----------

// in Shop\Domain\EdgeRouter
public function routable($e): bool
{
    return $e instanceof Wire && $e->from instanceof Port && $e->to instanceof Port;
}

// in Shop\Domain\EdgeRouter
public function tag($e): string
{
    $wired = $e instanceof Wire && $e->from instanceof Port && $e->to instanceof Port;

    return $wired ? 'wired' : 'loose';
}

// in Shop\Domain\TreeGuard
public function graftable($node): bool
{
    return $node instanceof Leaf && $node->parent instanceof Branch;
}

// in Shop\Domain\TreePruner
public function keep($node): bool
{
    if ($node instanceof Leaf && $node->parent instanceof Branch) {
        return false;
    }

    return true;
}

// in Shop\Domain\InvoiceRules
public function taxable($line): bool
{
    return $line instanceof SaleLine && $line->product instanceof TaxedGood;
}

// in Shop\Domain\InvoiceRules
public function band($line): string
{
    return match ($line instanceof SaleLine && $line->product instanceof TaxedGood) {
        true => 'vat',
        default => 'net',
    };
}

// in Shop\Domain\TokenScanner
public function opens($t): bool
{
    return $t instanceof Bracket && $t->pair instanceof Bracket;
}

// in Shop\Domain\TokenScanner
public function pairs(array $tokens): int
{
    $count = 0;

    foreach ($tokens as $t) {
        $count += $t instanceof Bracket && $t->pair instanceof Bracket ? 1 : 0;
    }

    return $count;
}

----------[ Good ]----------

// The narrowing promoted to a NAME: `isBalancedBrace()` holds `$t instanceof Brace &&
// $t->close instanceof Brace` once, and every site asks the predicate instead of copying the chain.

public function balanced(array $tokens): int
{
    return count(array_filter($tokens, $this->isBalancedBrace(...)));
}
```
