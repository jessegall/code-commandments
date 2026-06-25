<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\SetReturnContractProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class SetReturnContractProphetTest extends TestCase
{
    private SetReturnContractProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new SetReturnContractProphet;
    }

    private function judge(string $code): Judgment
    {
        return $this->prophet->judge('/x.php', $code);
    }

    public function test_flags_a_keyed_get_lookup_on_a_marked_set(): void
    {
        $j = $this->judge('<?php namespace App; class EmitterSet { private array $items = []; public function get(string $key): object { return $this->items[$key]; } }');

        $this->assertTrue($j->isFallen());
        $this->assertStringContainsString('keyed value lookup', $j->sins[0]->message);
        $this->assertSame('set-return:get', $j->sins[0]->symbol);
    }

    public function test_flags_an_option_return_on_a_marked_set(): void
    {
        $j = $this->judge('<?php namespace App; class EmitterSet { public function first(): Option { return Option::none(); } }');
        $this->assertTrue($j->isFallen());
        $this->assertStringContainsString('Option', $j->sins[0]->message);
    }

    public function test_flags_a_nullable_non_finder_getter_on_a_marked_set(): void
    {
        $j = $this->judge('<?php namespace App; class EmitterSet { public function pick(): ?object { return null; } }');
        $this->assertTrue($j->isFallen());
    }

    public function test_does_not_flag_the_total_set_surface(): void
    {
        $j = $this->judge('<?php namespace App; class EmitterSet { private array $items = []; public function add(object $e): void { $this->items[] = $e; } public function has(object $e): bool { return in_array($e, $this->items, true); } public function all(): array { return $this->items; } public function values(): array { return $this->items; } }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_does_not_flag_a_nullable_finder(): void
    {
        $j = $this->judge('<?php namespace App; class EmitterSet { public function findFirst(): ?object { return null; } }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_does_not_flag_an_unmarked_class(): void
    {
        // No Set marker → no enforcement (marker-driven, like RegistryReturnContract).
        $j = $this->judge('<?php namespace App; class Things { public function get(string $key): object { return new \stdClass(); } }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_honors_a_set_attribute_marker(): void
    {
        $j = $this->judge('<?php namespace App; #[Set] class Emitters { public function get(string $key): object { return new \stdClass(); } }');
        $this->assertTrue($j->isFallen());
    }

    public function test_does_not_flag_a_no_arg_getter(): void
    {
        // A no-arg getter is not a keyed lookup.
        $j = $this->judge('<?php namespace App; class EmitterSet { public function getDefault(): object { return new \stdClass(); } }');
        $this->assertTrue($j->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }
}
