<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferCoalesceFactoryProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferCoalesceFactoryProphetTest extends TestCase
{
    private PreferCoalesceFactoryProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferCoalesceFactoryProphet;
    }

    public function test_flags_new_value_bag_with_coalesce_default(): void
    {
        $j = $this->judge('class ValueBag extends \Illuminate\Support\Fluent {}
        class C { public function a($v) { return new ValueBag($v ?? []); } }');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('ValueBag::coalesce', $j->warnings[0]->message);
    }

    public function test_flags_t_array_coalesce_arg(): void
    {
        $j = $this->judge('class ValueBag extends \Illuminate\Support\Fluent {}
        class C { public function a($v) { return new ValueBag(\JesseGall\PhpTypes\T_Array::coalesce($v)); } }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_flags_is_array_ternary_guard(): void
    {
        $j = $this->judge('class ValueBag extends \Illuminate\Support\Fluent {}
        class C { public function a($v) { return new ValueBag(is_array($v) ? $v : []); } }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_flags_make_named_constructor(): void
    {
        $j = $this->judge('class ValueBag extends \Illuminate\Support\Fluent {}
        class C { public function a($v) { return ValueBag::make($v ?? []); } }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_flags_t_array_empty_constant(): void
    {
        $j = $this->judge('class Bag extends \Illuminate\Support\Fluent {}
        class C { public function a($v) { return new Bag($v ?? \JesseGall\PhpTypes\T_Array::EMPTY); } }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_ignores_already_typed_construction(): void
    {
        $this->assertTrue($this->judge('class ValueBag extends \Illuminate\Support\Fluent {}
        class C { public function a(array $v) { return new ValueBag($v); } }')->isRighteous());
    }

    public function test_flags_own_array_constructor_without_a_known_base(): void
    {
        // Real detection: no Fluent/Collection base — just a class whose own
        // constructor's first param is an array.
        $j = $this->judge('class Bag { public function __construct(array $items) {} }
        class C { public function a($v) { return new Bag($v ?? []); } }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_flags_untyped_array_default_constructor(): void
    {
        // Fluent/Collection shape: untyped first param with an array default.
        $j = $this->judge('class Bag { public function __construct($items = []) {} }
        class C { public function a($v) { return new Bag($v ?? []); } }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_ignores_a_non_array_constructor(): void
    {
        // Constructor takes a string, not an array — not this pattern (this is
        // why a hardcoded "value base" list was wrong; we check the real ctor).
        $this->assertTrue($this->judge('class PhoneNumber { public function __construct(string $e164) {} }
        class C { public function a($v) { return new PhoneNumber($v ?? []); } }')->isRighteous());
    }

    public function test_ignores_non_value_object_class(): void
    {
        // A service with no constructor is not array-constructible.
        $this->assertTrue($this->judge('class Service {}
        class C { public function a($v) { return new Service($v ?? []); } }')->isRighteous());
    }

    public function test_ignores_unresolvable_class(): void
    {
        // Class not defined here and no index → cannot prove value-object; leave it.
        $this->assertTrue($this->judge('class C { public function a($v) { return new \Vendor\Unknown($v ?? []); } }')->isRighteous());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
