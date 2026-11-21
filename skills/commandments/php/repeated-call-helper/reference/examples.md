# Promote a repeated call to a method — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### repeated-query-chain

One Eloquent query chain written alike at three or more sites outside its model

```php
----------[ Bad ]----------

public function redeem(int $cardId, int $amountCents): int
{
    $card = GiftCard::query()->whereKey($cardId)->lockForUpdate()->sole();

    return min($amountCents, $card->balance_cents);
}

----------[ Good ]----------

// in Shop\Payments\GiftCardRedemption
public function coverable(int $cardId, int $amountCents): int
{
    return min($amountCents, GiftCard::lockedFor($cardId)->balance_cents);
}

// in Shop\Models\GiftCard
public static function lockedFor(int $cardId): self
{
    return self::query()->whereKey($cardId)->lockForUpdate()->sole();
}
```

### repeated-named-call

The same `with`-style (variadic) method is called with the same named argument at 2+ sites, instead of a named helper on the type

```php
----------[ Bad ]----------

// in Shop\Http\Pages\RepeatedCall\CardHydrator
public function hydrate(UiNode $node, string $title, string $state): UiNode
{
    return $node->copyWith(metadata: CardMeta::from(['title' => $title, 'tone' => $this->tone($state)])->toArray());
}

// in Shop\Http\Pages\RepeatedCall\PanelHydrator
public function hydrate(UiNode $node, ?string $heading): UiNode
{
    $heading = $this->normalise($heading);

    return $node->copyWith(metadata: PanelMeta::from(['heading' => $heading])->toArray());
}

// in Shop\Http\Pages\RepeatedCall\PortHydrator
public function hydrate(UiNode $node, string $name): UiNode
{
    $required = in_array($name, $this->requiredPorts, true);

    return $node->copyWith(metadata: PortMeta::from(['name' => $name, 'required' => $required])->toArray());
}

----------[ Good ]----------

// in Shop\Http\Pages\RepeatedCall\CardHydrator
public function decorate(UiNode $node, CardMeta $meta): UiNode
{
    return $node->withMetadata($meta);
}

// in Shop\Http\Pages\RepeatedCall\WithChanges
public function withMetadata(Data $meta): static
{
    return $this->copyWith(metadata: $meta->toArray());
}
```
