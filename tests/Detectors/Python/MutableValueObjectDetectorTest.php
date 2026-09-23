<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\MutableValueObjectDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class MutableValueObjectDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new MutableValueObjectDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a field assigned' => ["@dataclass\nclass Money:\n    amount: int\n    currency: str\n\n    def add(self, other):\n        self.amount += other.amount\n"];
        yield 'a frozen one written through object.__setattr__' => ["@dataclass(frozen=True)\nclass Price:\n    amount: int\n\n    def discount(self, pct):\n        object.__setattr__(self, 'amount', self.amount * (100 - pct) // 100)\n"];
        yield 'a field reassigned' => ["@dataclass\nclass Address:\n    street: str\n    city: str\n\n    def move(self, street, city):\n        self.street = street\n        self.city = city\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'derived instead' => ["@dataclass(frozen=True)\nclass Money:\n    amount: int\n\n    def add(self, other):\n        return replace(self, amount=self.amount + other.amount)\n"];
        yield 'normalised in __post_init__' => ["@dataclass(frozen=True)\nclass Code:\n    value: str\n\n    def __post_init__(self):\n        object.__setattr__(self, 'value', self.value.upper())\n"];
        yield 'working state kept out of init' => ["@dataclass\nclass Import:\n    rows: list\n    done: int = field(default=0, init=False)\n\n    def step(self):\n        self.done += 1\n"];
        yield 'a memo filled once' => ["@dataclass\nclass Request:\n    root: str\n    kept: object = None\n\n    def record(self):\n        if self.kept is None:\n            self.kept = load(self.root)\n        return self.kept\n"];
        yield 'not a dataclass' => ["class Counter:\n    def __init__(self, n):\n        self.n = n\n\n    def bump(self):\n        self.n += 1\n"];
    }
}
