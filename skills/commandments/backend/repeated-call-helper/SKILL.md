---
name: commandments-backend-repeated-call-helper
description: "When you keep calling the same `with`-style (variadic, named-argument) method the same way — `$element->copyWith(metadata: SomeData::from([...])->toArray())` at site after site — the repeated call plus its construction boilerplate is a missing method on the receiver's type. Read this when a `->with…(named: …)` / `->copyWith(named: …)` call, especially one wrapping a `Data::from([...])->toArray()`, recurs across call sites."
---

# Promote a repeated call to a method

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A call you write the same way over and over is a method you haven't named yet. When the same
> named argument, with the same construction boilerplate, rides a `with`-style API at site after site, give
> the receiver's type the operation: one `withMetadata($payload)` that hides the call AND the `from(…)->toArray()`
> wrap.

## The principle

A variadic, named-argument method — `copyWith(mixed ...$changes)`, a `with…()` builder — is a flexible,
low-level API: it maps whatever named arguments you pass onto the object. That flexibility is the point at
ONE call site. Across many, writing the *same* named argument the *same* way each time is duplication with
a tell: you've discovered a specific operation the type should name.

### The smell

The same shape recurs at site after site:

```php
$element->copyWith(metadata: PortInputPayload::from([...])->toArray());
```

Three things repeat every time: the `copyWith(metadata: …)` call, the `PortInputPayload::from([...])` build,
and the `->toArray()` flatten — your **two arrays** (the literal you write, then the array `toArray()`
returns). The low-level `with`-call is doing a specific job that has no name.

### The fix: name the operation on the type

Give the receiver's type the method the call sites keep spelling out:

```php
public function withMetadata(PortInputPayload $payload): static
{
    return $this->copyWith(metadata: $payload->toArray());
}
```

Now every site is `$element->withMetadata($payload)` — the `copyWith` mapping and the `from(…)->toArray()`
wrap live in ONE place, named for what they do. The generic `copyWith` stays for the genuinely one-off
change; the operation you kept repeating becomes first-class.

### When it is NOT this sin

- **A one-off.** A `with`-call written once is exactly what the flexible API is for — no method needed.
- **Genuinely different arguments.** If each site passes a *different* named argument (`copyWith(label: …)`
  here, `copyWith(icon: …)` there), there is no single operation to name.
- **Unrelated types.** Two different classes that both happen to have a `copyWith` are not one operation;
  only calls that resolve to the *same* method (the same class, or one it inherits) count as repetition.

## Rules

- Promote a recurring compound guard to a named predicate. The same condition — however its conjuncts are ordered, and whether it reads `$obj->x` inline or a local aliased from it — belongs in ONE named method.
  _Extract the repeated condition into a named boolean method and call THAT at each site._
- Promote a repeated `->with…(named: …)` call into a method on the receiver's type that hides the call and its construction boilerplate.
  _`$element->withMetadata($payload)` — a `withMetadata()` on the type doing `copyWith(metadata: $payload->toArray())`._
- Promote a recurring `instanceof` chain to a named predicate (`$x->isNewOfNamedClass()`), so the intent has a name and the narrowing has ONE home.
  _Extract the repeated `instanceof` chain into a named boolean method and call THAT at each site._

## Bad → good

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

## When it fires

- The SAME compound guard condition recurs in ≥2 places — the same check spelled differently (inline reaches vs locals) or reordered still counts, so a copied condition has no name — `RepeatedGuardDetector`
- The same `with`-style (variadic) method is called with the same named argument at 2+ sites, instead of a named helper on the type — `RepeatedNamedCallDetector`
- The SAME multi-`instanceof` type-narrowing guard (`$x instanceof A && $x->y instanceof B`) is written verbatim in ≥2 places — a check with no name, copied instead of named — `RepeatedTypeGuardDetector`

## Checklist

- [ ] Promote a recurring compound guard to a named predicate. The same condition — however its conjuncts are ordered, and whether it reads `$obj->x` inline or a local aliased from it — belongs in ONE named method.
- [ ] Promote a repeated `->with…(named: …)` call into a method on the receiver's type that hides the call and its construction boilerplate.
- [ ] Promote a recurring `instanceof` chain to a named predicate (`$x->isNewOfNamedClass()`), so the intent has a name and the narrowing has ONE home.
