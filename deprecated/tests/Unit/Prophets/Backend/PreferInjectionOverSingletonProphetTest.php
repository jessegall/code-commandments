<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferInjectionOverSingletonProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferInjectionOverSingletonProphetTest extends TestCase
{
    private PreferInjectionOverSingletonProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferInjectionOverSingletonProphet;
    }

    public function test_flags_a_coalesce_cached_singleton(): void
    {
        $judgment = $this->judge(<<<'PHP'
        final class Config
        {
            private static ?self $instance = null;
            private function __construct(private array $values) {}
            public static function getInstance(): self
            {
                return self::$instance ??= new self([]);
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('singleton:Config', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('Inject it instead', $judgment->warnings[0]->message);
    }

    public function test_flags_an_if_null_cached_singleton_by_shape_not_name(): void
    {
        // Not named getInstance — detected by the static-property cache shape.
        $judgment = $this->judge(<<<'PHP'
        class Database
        {
            private static $conn;
            protected function __construct() {}
            public static function connection(): self
            {
                if (self::$conn === null) {
                    self::$conn = new self();
                }
                return self::$conn;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_flag_a_memo_of_other_values(): void
    {
        // A keyed cache of OTHER values (not `new self`) is a memo, not a singleton.
        $judgment = $this->judge(<<<'PHP'
        class Resolver
        {
            private static array $cache = [];
            private function __construct() {}
            public static function get(string $key)
            {
                return self::$cache[$key] ??= strlen($key);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_public_ctor_or_a_manual_enum(): void
    {
        $publicCtor = $this->judge(<<<'PHP'
        class Service
        {
            private static ?self $instance = null;
            public function __construct() {}
            public static function make(): self { return self::$instance ??= new self(); }
        }
        PHP);
        $this->assertTrue($publicCtor->isRighteous(), 'a public ctor means it is freely constructible, not a singleton');

        $manualEnum = $this->judge(<<<'PHP'
        final class Suit
        {
            private function __construct(public string $v) {}
            public static function hearts(): self { return new self('H'); }
            public static function spades(): self { return new self('S'); }
        }
        PHP);
        $this->assertTrue($manualEnum->isRighteous(), 'a closed set of distinct instances is a manual enum, not a singleton');
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
