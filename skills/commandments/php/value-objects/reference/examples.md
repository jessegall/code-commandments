# Value objects — give related data a type — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### flat-field-cluster — in PHP

A `#[TypeScript]` `Data` class spreads a value object it already models flat across sibling scalar fields sharing a camelCase prefix (`wireType` + `wireLabel`) instead of nesting the existing `Wire{type, label}`.

```php
----------[ Bad ]----------

/*
 * PortView restates the existing Wire value object FLAT as wire{Type,Socket,Label} instead of nesting a
 * single `wire: Wire`. The flat trio travels to the frontend and should be one depth-nested sub-object.
 */
#[TypeScript]
final class PortView extends Data
{
    public function __construct(
        public readonly string $wireType,
        public readonly string $wireSocket,
        public readonly string $wireLabel,
        public readonly int $index,
    ) {}

    public function slot(): string
    {
        return $this->wireSocket . '#' . $this->index;
    }

    public function isBus(): bool
    {
        return $this->wireType === 'bus' || $this->wireType === 'backplane';
    }

    public function ordinal(): string
    {
        return match (true) {
            $this->index === 0 => 'primary',
            $this->index < 4 => 'secondary',
            default => 'auxiliary',
        };
    }

    public function pinout(string $prefix): string
    {
        return strtoupper($prefix) . '/' . $this->wireLabel . '/' . $this->slot();
    }
}

----------[ Good ]----------

// The same view with its depth restored: the wire{Type,Socket,Label} trio is NESTED as the one `Wire` the
// codebase already declares, so each member sheds the prefix and the value object is modelled once.

#[TypeScript]
final class NestedPortView extends Data
{
    public function __construct(
        public readonly Wire $wire,
        public readonly int $index,
    ) {}
}

/* The Wire value object: a port's wiring identity, {type, socket, label}. */
#[TypeScript]
final class Wire extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly string $socket,
        public readonly string $label,
    ) {}
}
```
