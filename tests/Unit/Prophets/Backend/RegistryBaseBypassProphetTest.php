<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\RegistryBaseBypassProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use PHPUnit\Framework\TestCase;

class RegistryBaseBypassProphetTest extends TestCase
{
    private RegistryBaseBypassProphet $prophet;

    protected function setUp(): void
    {
        $this->prophet = new RegistryBaseBypassProphet();
    }

    public function test_flags_a_registry_subclass_that_bypasses_the_base_store(): void
    {
        // #119: overrides all() to a private store; inherits register() → dead.
        $judgment = $this->judge(<<<'PHP'
class ResourceRegistry extends Registry
{
    private array|null $resources = null;
    public function all(): array { return $this->resources ??= $this->build(); }
    private function build(): array { return []; }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('registry-base-bypass:ResourceRegistry', $judgment->warnings[0]->symbol);
    }

    public function test_does_not_flag_when_override_delegates_to_parent(): void
    {
        $judgment = $this->judge(<<<'PHP'
class CachedRegistry extends Registry
{
    private array $extra = [];
    public function all(): array { return [...parent::all(), ...$this->extra]; }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_when_subclass_overrides_the_mutators(): void
    {
        // It feeds its own store via its own register() — consistent, not dead.
        $judgment = $this->judge(<<<'PHP'
class ThingRegistry extends Registry
{
    private array $things = [];
    public function register(string $k, $v): void { $this->things[$k] = $v; }
    public function all(): array { return $this->things; }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_class_that_does_not_extend_a_registry_base(): void
    {
        $judgment = $this->judge(<<<'PHP'
class Catalog extends ArrayObject
{
    private array $things = [];
    public function all(): array { return $this->things; }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_when_no_own_array_store(): void
    {
        $judgment = $this->judge(<<<'PHP'
class TypedRegistry extends Registry
{
    public function all(): array { return $this->compute(); }
    private function compute(): array { return []; }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n\nnamespace App;\n\n{$body}\n");
    }
}
