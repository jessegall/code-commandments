# Laravel idioms — typed access, injected deps, behaviour on the model — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### hard-coded-route-path — in PHP

A path written out by hand where a named route already declares it

```php
----------[ Bad ]----------

public function statementLink(GiftCard $card): string
{
    return "/gift-cards/{$card->id}/statement";
}

----------[ Good ]----------

public function statementUrl(GiftCard $card): string
{
    return route('gift-cards.statement', $card->id);
}
```
