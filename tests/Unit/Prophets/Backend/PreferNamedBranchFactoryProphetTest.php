<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferNamedBranchFactoryProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferNamedBranchFactoryProphetTest extends TestCase
{
    private PreferNamedBranchFactoryProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferNamedBranchFactoryProphet;
    }

    public function test_flags_a_this_capturing_factory_that_does_work(): void
    {
        $judgment = $this->judge('$x->then(fn ($r) => \CFT::object($this->objects->slugForToken($r->type)->getOrThrow()));');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('*Factory', $judgment->warnings[0]->message);
    }

    public function test_flags_a_multi_statement_closure_capturing_this(): void
    {
        $judgment = $this->judge('$x->then(function ($r) { $v = $this->build($r); return $v; });');

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_leaves_a_trivial_constant_or_enum_closure(): void
    {
        $this->assertTrue($this->judge('$x->then(fn () => \SchemaFieldType::Int);')->isRighteous());
        $this->assertTrue($this->judge('$x->then(fn () => self::DEFAULT);')->isRighteous());
    }

    public function test_leaves_a_bare_property_return(): void
    {
        $this->assertTrue($this->judge('$x->then(fn () => $this->config);')->isRighteous());
    }

    public function test_leaves_a_first_class_callable_or_named_ref(): void
    {
        $this->assertTrue($this->judge('$x->then(\Capture::make());')->isRighteous());
        $this->assertTrue($this->judge('$x->then(WireType::scalar(...));')->isRighteous());
    }

    public function test_leaves_a_closure_that_does_not_capture_this(): void
    {
        // No dependency to home on a factory class — keep it inline.
        $this->assertTrue($this->judge('$x->then(fn ($r) => \CFT::scalar((string) $r->type));')->isRighteous());
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
