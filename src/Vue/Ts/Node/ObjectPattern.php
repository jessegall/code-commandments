<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * An object-destructuring pattern — `const { taxes, taxRate, formatTaxLabel } = useTaxTypes()`,
 * with aliasing (`{ a: b }` binds `b` from key `a`) and rest (`{ ...rest }`). It maps each bound
 * local to the KEY it came from ({@see keyFor}), the hop a composable trace needs: the local
 * `taxes` pulls the `taxes` field off `useTaxTypes`' return type.
 */
final class ObjectPattern extends Pattern
{
    /**
     * @param  array<string, string>  $entries  local name => source key (equal for a plain `{ a }`)
     * @param  ?string  $rest  the `...rest` local, if any
     */
    public function __construct(
        public readonly array $entries,
        public readonly ?string $rest = null,
    ) {}

    public function names(): array
    {
        return array_values(array_filter([...array_keys($this->entries), $this->rest], static fn (?string $n): bool => $n !== null));
    }

    /**
     * The source key a bound local came from — `{ a: b }` → `keyFor('b') === 'a'`; null if unbound.
     */
    public function keyFor(string $local): ?string
    {
        return $this->entries[$local] ?? null;
    }

    public function render(): string
    {
        $parts = [];

        foreach ($this->entries as $local => $key) {
            $parts[] = $key === $local ? $local : "{$key}: {$local}";
        }

        if ($this->rest !== null) {
            $parts[] = '...' . $this->rest;
        }

        return '{ ' . implode(', ', $parts) . ' }';
    }
}
