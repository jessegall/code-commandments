<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\ValueObjects;

final class DictionaryBag extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-dictionary-bag',
            skill: ValueObjects::class,
            description: 'A string-keyed dictionary or JSON object read by keys written in the source — `row["sku"]`, `json.GetProperty("name")` — a record nobody declared',
            rule: 'Give a record a type — an immutable `record` — instead of a dictionary read by string keys.',
            suggestion: 'Declare the keys as members of a record, build it where the data enters (`JsonSerializer.Deserialize<T>` or a static `From` factory), and take that type from there on.',
        );
    }
}
