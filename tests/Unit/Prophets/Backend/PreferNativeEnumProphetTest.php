<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferNativeEnumProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferNativeEnumProphetTest extends TestCase
{
    private PreferNativeEnumProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferNativeEnumProphet;
    }

    public function test_flags_a_hand_rolled_enum(): void
    {
        $judgment = $this->judge(<<<'PHP'
        final class Suit
        {
            private function __construct(public readonly string $value) {}
            public static function hearts(): self { return new self('H'); }
            public static function spades(): self { return new self('S'); }
            public static function diamonds(): self { return new self('D'); }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('manual-enum:Suit', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('native `enum`', $judgment->warnings[0]->message);
    }

    public function test_flags_a_protected_ctor_with_new_static_cases(): void
    {
        // Detected by SHAPE — protected ctor + `new static` parameterless cases.
        $judgment = $this->judge(<<<'PHP'
        class Color
        {
            protected function __construct(private string $hex) {}
            public static function red(): static { return new static('f00'); }
            public static function blue(): static { return new static('00f'); }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_flag_a_value_object_with_a_parameterised_factory(): void
    {
        // An OPEN value type — a parameterised `fromCents` factory means the
        // instances are not a closed set, so it is not an enumeration.
        $judgment = $this->judge(<<<'PHP'
        final class Money
        {
            private function __construct(public int $cents) {}
            public static function zero(): self { return new self(0); }
            public static function fromCents(int $cents): self { return new self($cents); }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_singleton_or_public_ctor_factory(): void
    {
        $singleton = $this->judge(<<<'PHP'
        class Registry
        {
            private static ?self $instance = null;
            private function __construct() {}
            public static function getInstance(): self { return self::$instance ??= new self(); }
        }
        PHP);
        $this->assertTrue($singleton->isRighteous(), 'one static accessor is a singleton, not an enum');

        $publicCtor = $this->judge(<<<'PHP'
        class Point
        {
            public function __construct(public int $x) {}
            public static function a(): self { return new self(1); }
            public static function b(): self { return new self(2); }
        }
        PHP);
        $this->assertTrue($publicCtor->isRighteous(), 'a public ctor means instances are freely constructible');
    }

    public function test_does_not_flag_a_native_enum(): void
    {
        $judgment = $this->judge(<<<'PHP'
        enum Suit: string
        {
            case Hearts = 'H';
            case Spades = 'S';
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
