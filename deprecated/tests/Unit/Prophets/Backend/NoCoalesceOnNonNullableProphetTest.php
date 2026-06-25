<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoCoalesceOnNonNullableProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoCoalesceOnNonNullableProphetTest extends TestCase
{
    private NoCoalesceOnNonNullableProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoCoalesceOnNonNullableProphet();
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\nclass C {\n{$body}\n}\n");
    }

    public function test_flags_coalesce_on_a_nonnullable_param(): void
    {
        $j = $this->judge('public function f(int $version): int { return $version ?? 1; }');

        $this->assertCount(1, $j->sins);
        $this->assertStringContainsString('never null', $j->sins[0]->message);
    }

    public function test_flags_coalesce_on_a_nonnullable_param_inside_a_cast(): void
    {
        $j = $this->judge('public function f(int $version): int { return (int) ($version ?? 1); }');

        $this->assertCount(1, $j->sins);
    }

    public function test_flags_a_promoted_nonnullable_property(): void
    {
        $j = $this->judge("public function __construct(private int \$v) {}\npublic function f(): int { return \$this->v ?? 1; }");

        $this->assertCount(1, $j->sins);
    }

    public function test_leaves_a_nullable_param(): void
    {
        $this->assertTrue($this->judge('public function f(?int $version): int { return $version ?? 1; }')->isRighteous());
    }

    public function test_leaves_a_union_with_null(): void
    {
        $this->assertTrue($this->judge('public function f(int|null $version): int { return $version ?? 1; }')->isRighteous());
    }

    public function test_leaves_a_mixed_param(): void
    {
        $this->assertTrue($this->judge('public function f(mixed $version): int { return $version ?? 1; }')->isRighteous());
    }

    public function test_leaves_an_untyped_param(): void
    {
        $this->assertTrue($this->judge('public function f($version): int { return $version ?? 1; }')->isRighteous());
    }

    public function test_leaves_a_declared_property_without_a_default(): void
    {
        // A declared typed property with no default may be uninitialized — `??` is a legit guard.
        $j = $this->judge("private int \$v;\npublic function f(): int { return \$this->v ?? 1; }");

        $this->assertTrue($j->isRighteous());
    }
}
