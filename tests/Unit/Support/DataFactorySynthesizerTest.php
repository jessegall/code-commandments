<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Prophets\Backend\ExplicitDataFactoryProphet;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

/**
 * #44: repent should synthesise the fromX() factory for object-typed
 * `XData::from($obj)` and rewrite the call — including cross-file, via the index.
 */
class DataFactorySynthesizerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-synth-' . uniqid();
        @mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function write(string $name, string $php): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $php);

        return $path;
    }

    private function repent(string $path, ?CodebaseIndex $index = null)
    {
        $prophet = new ExplicitDataFactoryProphet();

        if ($index !== null) {
            $prophet->setCodebaseIndex($index);
        }

        return $prophet->repent($path, file_get_contents($path));
    }

    public function test_same_file_rewrites_call_and_generates_factory(): void
    {
        $path = $this->write('Same.php', <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        class Shop { public function toArray(): array { return []; } public function toData(): ShopData { return ShopData::from($this); } }
        class ShopData extends Data { public int $id; }
        PHP);

        $result = $this->repent($path);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('ShopData::fromShop($this)', $result->newContent);
        $this->assertStringContainsString('public static function fromShop(\App\Shop $shop): static', $result->newContent);
        $this->assertStringContainsString('return static::from($shop->toArray());', $result->newContent);
        $this->assertSame([], $result->createdFiles);
    }

    public function test_cross_file_generates_factory_in_the_other_file(): void
    {
        $caller = $this->write('Shop.php', <<<'PHP'
        <?php
        namespace App\Models;
        use App\Data\ShopData;
        class Shop { public function toArray(): array { return []; } public function toData(): ShopData { return ShopData::from($this); } }
        PHP);
        $dataFile = $this->write('ShopData.php', <<<'PHP'
        <?php
        namespace App\Data;
        use Spatie\LaravelData\Data;
        class ShopData extends Data { public int $id; }
        PHP);

        $index = CodebaseIndex::build([$caller, $dataFile]);
        $result = $this->repent($caller, $index);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('ShopData::fromShop($this)', $result->newContent);
        $this->assertArrayHasKey($dataFile, $result->createdFiles);
        $this->assertStringContainsString('public static function fromShop(\App\Models\Shop $shop): static', $result->createdFiles[$dataFile]);
    }

    public function test_resolves_param_property_new_and_closure(): void
    {
        $path = $this->write('Many.php', <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        class Shop {}
        class Holder { public function __construct(public Shop $shop) {} }
        class ShopData extends Data {
            public static function ofParam(Shop $shop): static { return ShopData::from($shop); }
            public static function ofNew(): static { return ShopData::from(new Shop()); }
            public static function ofClosure(array $shops): array { return array_map(fn (Shop $s) => ShopData::from($s), $shops); }
        }
        PHP);

        $result = $this->repent($path);

        $this->assertTrue($result->absolved);
        // One factory, deduped across the param/new/closure sites (all type Shop).
        $this->assertSame(1, substr_count($result->newContent, 'public static function fromShop('));
        $this->assertSame(3, substr_count($result->newContent, 'ShopData::fromShop('));
    }

    public function test_is_idempotent_when_factory_already_exists(): void
    {
        $path = $this->write('Idem.php', <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        class Shop {}
        class ShopData extends Data {
            public static function fromShop(Shop $shop): static { return static::from([]); }
            public static function go(Shop $shop): static { return ShopData::from($shop); }
        }
        PHP);

        $result = $this->repent($path);

        // The call is rewritten, but no second fromShop() is generated.
        $this->assertStringContainsString('ShopData::fromShop($shop)', $result->newContent);
        $this->assertSame(1, substr_count($result->newContent, 'public static function fromShop('));
    }

    public function test_unreachable_data_class_is_left_untouched(): void
    {
        // No index, and ShopData is not defined in this file — repent cannot place
        // the factory, so it must NOT rewrite the call (no dangling reference).
        $path = $this->write('Orphan.php', <<<'PHP'
        <?php
        namespace App;
        class Shop { public function toData(): mixed { return \Vendor\ShopData::from($this); } }
        PHP);

        $result = $this->repent($path);

        $this->assertFalse($result->absolved, 'No change when the Data class is unreachable.');
    }
}
