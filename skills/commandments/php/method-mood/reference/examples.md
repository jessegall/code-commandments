# Method mood — an order, or a question — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### bare-state-predicate

A `bool` about the object's own state named as a bare verb — `binds()`, `spins()` — where a question belongs

```php
----------[ Bad ]----------

// A bool about the line itself, named as a claim instead of a question.

public function reports(): bool
{
    return $this->level === 'error';
}

----------[ Good ]----------

// The FIX is the NAME: same body, same class, asked as a question. `if ($line->isErrored())`
// reads as English at the call site, where `if ($line->reports())` reads as a claim.

public function isErrored(): bool
{
    return $this->level === 'error';
}
```

### narrated-command

A command named in the third person — `hides()`, `entersTestMode()` — where a call is an order, not a description of one

```php
----------[ Bad ]----------

// The fluent form of the same mistake: chainable, still an order.

public function clears(): static
{
    $this->entries = [];

    return $this;
}

----------[ Good ]----------

// The FIX is the NAME: drop the -s. Still fluent, still the same body — but `$weights->clear()`
// is the order the call site is actually giving, instead of a description of one.

public function clear(): static
{
    $this->entries = [];

    return $this;
}
```
