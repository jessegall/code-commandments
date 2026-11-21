# Method mood — an order, or a question — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### bare-state-predicate

A `bool` about the object's own state named as a bare verb — `binds()`, `spins()` — where a question belongs

```php
----------[ Bad ]----------

public function reports(): bool
{
    return $this->level === 'error';
}

----------[ Good ]----------

public function isErrored(): bool
{
    return $this->level === 'error';
}
```

### narrated-command

A command named in the third person — `hides()`, `entersTestMode()` — where a call is an order, not a description of one

```php
----------[ Bad ]----------

public function clears(): static
{
    $this->entries = [];

    return $this;
}

----------[ Good ]----------

public function clear(): static
{
    $this->entries = [];

    return $this;
}
```
