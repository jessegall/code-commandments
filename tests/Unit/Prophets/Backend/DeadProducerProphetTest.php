<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\DeadProducerProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class DeadProducerProphetTest extends TestCase
{
    private DeadProducerProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new DeadProducerProphet;
    }

    public function test_flags_a_private_producer_whose_result_is_always_discarded(): void
    {
        $judgment = $this->judge('private function f(): string { return "x"; } public function a() { $this->f(); $this->f(); }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('dead-producer:f', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('2 call site(s)', $judgment->warnings[0]->message);
    }

    public function test_flags_nullable_and_union_producers(): void
    {
        $this->assertTrue($this->judge('private function f(): ?Foo { return null; } public function a() { $this->f(); }')->hasWarnings());
        $this->assertTrue($this->judge('private function f(): int|string { return 1; } public function a() { $this->f(); }')->hasWarnings());
    }

    public function test_does_not_flag_when_a_result_is_used(): void
    {
        $this->assertTrue($this->judge('private function f(): string { return "x"; } public function a() { $x = $this->f(); }')->isRighteous());
        $this->assertTrue($this->judge('private function f(): string { return "x"; } public function a() { return $this->f(); }')->isRighteous());
        $this->assertTrue($this->judge('private function f(): string { return "x"; } public function a() { g($this->f()); }')->isRighteous());
    }

    public function test_does_not_flag_void_fluent_public_or_untyped(): void
    {
        $this->assertTrue($this->judge('private function f(): void { echo 1; } public function a() { $this->f(); }')->isRighteous());
        $this->assertTrue($this->judge('private function f(): self { return $this; } public function a() { $this->f(); }')->isRighteous());
        $this->assertTrue($this->judge('public function f(): string { return "x"; } public function a() { $this->f(); }')->isRighteous(), 'external callers unseen');
        $this->assertTrue($this->judge('private function f() { return 1; } public function a() { $this->f(); }')->isRighteous(), 'no declared type');
    }

    public function test_does_not_flag_when_uncalled_or_referenced_as_a_callable(): void
    {
        $this->assertTrue($this->judge('private function f(): string { return "x"; }')->isRighteous(), 'no in-class call');
        $this->assertTrue($this->judge('private function f(): string { return "x"; } public function a() { $fn = $this->f(...); $this->f(); }')->isRighteous());
        $this->assertTrue($this->judge('private function f(): string { return "x"; } public function a() { $c = [$this, "f"]; $this->f(); }')->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/tmp/x.php', "<?php\nnamespace App;\nclass C { {$body} }");
    }
}
