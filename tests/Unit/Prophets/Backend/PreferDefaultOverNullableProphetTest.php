<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferDefaultOverNullableProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferDefaultOverNullableProphetTest extends TestCase
{
    private PreferDefaultOverNullableProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferDefaultOverNullableProphet;
    }

    public function test_flags_a_nullable_whose_callers_all_default_with_a_constant(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class A {
            private function headerValue(string $k): ?string { return null; }
            public function len(): int { return (int) ($this->headerValue('a') ?? '0'); }
            public function key(): string { return $this->headerValue('b') ?? ''; }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('prefer-default:headerValue', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('$default', $judgment->warnings[0]->message);
    }

    public function test_flags_an_option_whose_callers_all_getOr_a_constant(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class A {
            private function find(string $k): \Option { return \Option::none(); }
            public function a(): string { return $this->find('a')->getOr('0'); }
            public function b(): int { return $this->find('b')->getOr(0); }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_flag_when_a_caller_handles_the_absence_differently(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class A {
            private function find(string $k): ?string { return null; }
            public function a(): string { return $this->find('a') ?? '0'; }
            public function b(): string { $x = $this->find('b'); if ($x === null) { throw new \E(); } return $x; }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_null_coalesce_or_a_non_constant_fallback(): void
    {
        $coalesceNull = $this->judge(<<<'PHP'
        class A {
            private function find(string $k): ?string { return null; }
            public function a(): ?string { return $this->find('a') ?? null; }
        }
        PHP);
        $this->assertTrue($coalesceNull->isRighteous(), '?? null is not a real default');

        $nonConstant = $this->judge(<<<'PHP'
        class A {
            private function find(string $k): ?string { return null; }
            public function a(): string { return $this->find('a') ?? $this->fallback(); }
            private function fallback(): string { return 'x'; }
        }
        PHP);
        $this->assertTrue($nonConstant->isRighteous(), 'a computed fallback is not a fixed default');
    }

    public function test_does_not_flag_a_public_method_or_a_non_maybe_return(): void
    {
        $public = $this->judge(<<<'PHP'
        class A {
            public function find(string $k): ?string { return null; }
            public function a(): string { return $this->find('a') ?? '0'; }
        }
        PHP);
        $this->assertTrue($public->isRighteous(), 'a public method\'s callers are not all visible here');

        $plain = $this->judge(<<<'PHP'
        class A {
            private function find(string $k): string { return ''; }
            public function a(): string { return $this->find('a') ?? '0'; }
        }
        PHP);
        $this->assertTrue($plain->isRighteous(), 'a non-maybe return is not the pattern');
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
