---
name: commandments-backend-repeated-call-helper
description: When you keep calling the same `with`-style (variadic, named-argument) method the same way — `$element->copyWith(metadata: SomeData::from([...])->toArray())` at site after site — the repeated call plus its construction boilerplate is a missing method on the receiver's type. Read this when a `->with…(named: …)` / `->copyWith(named: …)` call, especially one wrapping a `Data::from([...])->toArray()`, recurs across call sites.
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
// Bad
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

// Good
public function shippable($order, $address): bool
{
    return $order->paid && $address->confirmed;
}
```

### repeated-named-call

The same `with`-style (variadic) method is called with the same named argument at 2+ sites, instead of a named helper on the type

```php
// Bad
public function hydrate(UiNode $node, string $name): UiNode
{
    $required = in_array($name, $this->requiredPorts, true);

    return $node->copyWith(metadata: PortMeta::from(['name' => $name, 'required' => $required])->toArray());
}

// Good
public function decorate(UiNode $node, string $title): UiNode
{
    return $node->copyWith(chrome: CardMeta::from(['title' => $title, 'tone' => 'plain'])->toArray());
}
```

### repeated-type-guard

The SAME multi-`instanceof` type-narrowing guard (`$x instanceof A && $x->y instanceof B`) is written verbatim in ≥2 places — a check with no name, copied instead of named

```php
// Bad
public function routable($e): bool
{
    return $e instanceof Wire && $e->from instanceof Port && $e->to instanceof Port;
}

// Good
public function accepts($n): bool
{
    return $n instanceof Element && $n->attribute instanceof Marker;
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
