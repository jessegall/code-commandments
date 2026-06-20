# Case groups

When a recognisable **subset** of an enum's cases is inlined as an array literal,
it is a named concept in disguise. Give it a name **on the enum** and call that,
instead of inlining the subset where it is needed. If the same group is inlined
elsewhere the case is stronger still — every copy can drift independently — but a
single nameable inline group is enough to promote.

---

## Bad → good

```php
// BAD — two named groups, inlined by hand at the call site
$operators = $numeric
    ? [CompareOperator::Equals, CompareOperator::NotEquals, CompareOperator::GreaterThan,
       CompareOperator::GreaterOrEqual, CompareOperator::LessThan, CompareOperator::LessOrEqual]
    : [CompareOperator::Equals, CompareOperator::NotEquals, CompareOperator::StartsWith,
       CompareOperator::Contains, CompareOperator::EndsWith];
```

```php
// GOOD — each group is named once, on the enum
enum CompareOperator
{
    // …cases…

    /** @return list<self> */
    public static function numeric(): array
    {
        return [self::Equals, self::NotEquals, self::GreaterThan,
                self::GreaterOrEqual, self::LessThan, self::LessOrEqual];
    }

    /** @return list<self> */
    public static function textual(): array
    {
        return [self::Equals, self::NotEquals, self::StartsWith,
                self::Contains, self::EndsWith];
    }
}

$operators = $numeric ? CompareOperator::numeric() : CompareOperator::textual();
```

The group now has one home, one name, and one place to change. Callers ask the
enum "what are the numeric operators?" instead of re-listing them.

---

## What counts as a group

| Condition | Notes |
|---|---|
| ≥ 3 items (configurable `min_group`) | A pair is usually not worth a named accessor. |
| Every item is a plain `Enum::Case` of the **same** enum | A mix of enums, or any non-case item, disqualifies it. |
| Occurs ≥ `min_reuse` sites (default 1 — flagged on sight) | The group is canonicalised as the sorted, de-duplicated case set, so order and repetition inside the array don't matter; raise `min_reuse` to only flag truly duplicated groups. |
| NOT the haystack of `in_array(...)` / `array_search(...)` | That one-of membership test belongs to the CompareSelf `equalsAny` rule (see `reference/behavioural-dispatch.md`), not here. |
| NOT inside the enum's own file | That is exactly where the named-group accessor lives. |

---

## When to reach for it

- A recognisable subset of an enum's cases is inlined as an array AND the group
  has a clear, honest name — numeric, textual, terminal, editable, … The inline
  list is a named concept; name it on the enum.
- The same group appears in two or more places — every copy can drift; collapse
  to one accessor.

## When to leave it

- The array is a genuine **one-off** with no meaningful name — an arbitrary,
  ad-hoc selection that would never read well as a method on the enum.
- You cannot give the group an honest name. Only promote a group to a named
  accessor when you can name it and it is actually used. When unsure, leave it.

The name is semantic and cannot be inferred — the prophet points at the group;
you name it.

---

## Enforced by

`PreferEnumCaseGroups` — an inline subset of one enum's cases that reads as a
named group. Advisory, not auto-fixable (the name is yours to choose).

```
commandments:scripture --prophet=PreferEnumCaseGroups
```
