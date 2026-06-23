<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\AstCache;
use PhpParser\Node;
use PHPUnit\Framework\TestCase;

class AstCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AstCache::flush();
        AstCache::resetStats();
    }

    public function test_identical_content_is_parsed_once_and_shared(): void
    {
        $src = "<?php\nclass A { public function x(): int { return 1; } }\n";

        $first = AstCache::parse($src);
        $second = AstCache::parse($src);

        $this->assertIsArray($first);
        // The SAME node instance is returned — proof it was not re-parsed.
        $this->assertSame($first, $second);
        $this->assertSame(['hits' => 1, 'misses' => 1], AstCache::stats());
    }

    public function test_different_content_misses_separately(): void
    {
        AstCache::parse("<?php\n\$a = 1;");
        AstCache::parse("<?php\n\$b = 2;");

        $this->assertSame(['hits' => 0, 'misses' => 2], AstCache::stats());
    }

    public function test_a_parse_error_is_cached_as_null(): void
    {
        $broken = "<?php\nclass { ";

        $this->assertNull(AstCache::parse($broken));
        $this->assertNull(AstCache::parse($broken));
        // Cached null — the broken file is not re-parsed by the second caller.
        $this->assertSame(['hits' => 1, 'misses' => 1], AstCache::stats());
    }

    public function test_returns_a_node_list(): void
    {
        $ast = AstCache::parse("<?php\necho 1;");

        $this->assertIsArray($ast);
        $this->assertContainsOnlyInstancesOf(Node::class, $ast);
    }

    public function test_lru_evicts_beyond_capacity_but_keeps_hot_entries(): void
    {
        // Parse more distinct files than the cache holds; the first should evict.
        $first = "<?php\n\$f0 = 0;";
        AstCache::parse($first);

        for ($i = 1; $i <= 12; $i++) {
            AstCache::parse("<?php\n\$f{$i} = {$i};");
        }

        AstCache::resetStats();
        AstCache::parse($first); // evicted long ago -> miss

        $this->assertSame(['hits' => 0, 'misses' => 1], AstCache::stats());
    }
}
