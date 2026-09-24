<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\ValueObjects;

final class MutableValueObject extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-mutable-value-object',
            skill: ValueObjects::class,
            description: 'a record that can change after it is built — a `set` accessor, or a method that writes its own state — so two holders of the same value can end up seeing different things',
            rule: 'Build a record complete and never change it; derive a new one with `with` instead.',
            suggestion: 'Use `{ get; init; }` instead of `{ get; set; }`, and turn `void Add() { Items++; }` into `Cart Added() => this with { Items = Items + 1 };`.',
        );
    }
}
