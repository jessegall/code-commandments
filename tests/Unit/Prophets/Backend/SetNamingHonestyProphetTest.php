<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\SetNamingHonestyProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class SetNamingHonestyProphetTest extends TestCase
{
    private SetNamingHonestyProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new SetNamingHonestyProphet;
    }

    private function judge(string $code): Judgment
    {
        return $this->prophet->judge('/x.php', $code);
    }

    public function test_flags_a_set_shaped_class_with_a_vague_name(): void
    {
        $j = $this->judge('<?php namespace App; class EmitterStuff { private array $items = []; public function add(object $e): void { $this->items[] = $e; } public function all(): array { return $this->items; } }');

        $this->assertTrue($j->hasWarnings());
        $this->assertStringContainsString('EmitterStuff', $j->warnings[0]->message);
        $this->assertSame('set-naming:EmitterStuff', $j->warnings[0]->symbol);
    }

    public function test_does_not_flag_a_class_already_named_set(): void
    {
        $j = $this->judge('<?php namespace App; class EmitterSet { private array $items = []; public function add(object $e): void { $this->items[] = $e; } public function all(): array { return $this->items; } }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_does_not_flag_a_class_named_collection(): void
    {
        $j = $this->judge('<?php namespace App; class EmitterCollection { private array $items = []; public function add(object $e): void { $this->items[] = $e; } public function all(): array { return $this->items; } }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_does_not_flag_a_class_marked_with_a_set_base(): void
    {
        $j = $this->judge('<?php namespace App; class Emitters extends Set { private array $items = []; public function add(object $e): void { $this->items[] = $e; } public function all(): array { return $this->items; } }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_does_not_flag_a_registry(): void
    {
        $j = $this->judge('<?php namespace App; class ThingThing { private array $items = []; public function register(string $k, $v): void { $this->items[$k] = $v; } public function get(string $k) { return $this->items[$k]; } }');
        $this->assertTrue($j->isRighteous(), 'a keyed lookup is a registry concern, not a set');
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }
}
