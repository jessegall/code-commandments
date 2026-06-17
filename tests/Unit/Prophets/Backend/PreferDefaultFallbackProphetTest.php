<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferDefaultFallbackProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferDefaultFallbackProphetTest extends TestCase
{
    private PreferDefaultFallbackProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferDefaultFallbackProphet;
    }

    public function test_flags_a_same_receiver_presence_check_fallback(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Runner
        {
            public function go($context, $handle): void
            {
                $context->runBranch($context->hasBranch($handle) ? $handle : self::DEFAULT_HANDLE);
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('runBranch()', $judgment->warnings[0]->message);
        $this->assertStringContainsString('same receiver', strtolower($judgment->warnings[0]->message));
    }

    public function test_flags_a_this_receiver_check(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Runner
        {
            public function go($handle): void
            {
                $this->run($this->has($handle) ? $handle : 'default');
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_flag_a_guard_on_a_different_object(): void
    {
        // The callee may not own the data the guard tests — leave it.
        $judgment = $this->judge(<<<'PHP'
        class Runner
        {
            public function go($context, $registry, $handle): void
            {
                $context->runBranch($registry->hasBranch($handle) ? $handle : self::DEFAULT_HANDLE);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_short_ternary(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Runner
        {
            public function go($context, $handle): void
            {
                $context->runBranch($handle ?: self::DEFAULT_HANDLE);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_computed_fallback(): void
    {
        // A non-constant fallback means the call site is genuinely choosing.
        $judgment = $this->judge(<<<'PHP'
        class Runner
        {
            public function go($context, $handle): void
            {
                $context->runBranch($context->hasBranch($handle) ? $handle : $this->compute());
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_when_guard_tests_a_different_value(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Runner
        {
            public function go($context, $handle, $other): void
            {
                $context->runBranch($context->hasBranch($other) ? $handle : self::DEFAULT_HANDLE);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_non_presence_guard(): void
    {
        // computeBranch() is not a presence check — not a self-query smell.
        $judgment = $this->judge(<<<'PHP'
        class Runner
        {
            public function go($context, $handle): void
            {
                $context->runBranch($context->computeBranch($handle) ? $handle : self::DEFAULT_HANDLE);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
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
