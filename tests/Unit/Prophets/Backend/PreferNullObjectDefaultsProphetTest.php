<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferNullObjectDefaultsProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferNullObjectDefaultsProphetTest extends TestCase
{
    private PreferNullObjectDefaultsProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prophet = new PreferNullObjectDefaultsProphet;
        $this->prophet->configure([
            'null_objects' => [
                'callable' => 'App\\Support\\NullCallable',
                'LoggerInterface' => 'Psr\\Log\\NullLogger',
                'ShopCommandWorkerObserver' => 'App\\NullShopCommandWorkerObserver',
            ],
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // Pattern A1 — constant RHS (auto-fixable sin)
    // ────────────────────────────────────────────────────────────────

    public function test_flags_coalesce_assign_with_new_class_default_as_sin(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function run(ShopCommandWorkerObserver | null $observer = null): void {
                $observer ??= new NullShopCommandWorkerObserver;
                $observer->executing();
            }
        }
        PHP);

        $this->assertCount(1, $judgment->sins);
        $this->assertCount(0, $judgment->warnings);
        $this->assertStringContainsString('AUTO-FIXABLE', $judgment->sins[0]->message);
        $this->assertStringContainsString('$observer', $judgment->sins[0]->message);
        $this->assertStringContainsString('new NullShopCommandWorkerObserver', $judgment->sins[0]->suggestion);
    }

    public function test_flags_if_null_then_assign_block(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function run(Observer | null $observer = null): void {
                if ($observer === null) {
                    $observer = new NullObserver;
                }
                $observer->executing();
            }
        }
        PHP);

        $this->assertCount(1, $judgment->sins);
    }

    public function test_flags_full_coalesce_form(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function run(Observer | null $observer = null): void {
                $observer = $observer ?? new NullObserver;
                $observer->executing();
            }
        }
        PHP);

        $this->assertCount(1, $judgment->sins);
    }

    public function test_flags_nullable_shorthand_question_mark_syntax(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function run(?Observer $observer = null): void {
                $observer ??= new NullObserver;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->sins);
    }

    // ────────────────────────────────────────────────────────────────
    // Pattern A2 — closure RHS
    // ────────────────────────────────────────────────────────────────

    public function test_flags_closure_default_with_mapped_null_object_as_auto_fixable(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function run(callable | null $shouldExit = null): void {
                $shouldExit ??= static fn () => false;
                $shouldExit();
            }
        }
        PHP);

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('AUTO-FIXABLE', $judgment->sins[0]->message);
        $this->assertStringContainsString('new NullCallable', $judgment->sins[0]->suggestion);
    }

    public function test_flags_closure_default_without_mapped_null_object_as_unfixable_sin(): void
    {
        $prophet = new PreferNullObjectDefaultsProphet;
        $prophet->configure(['null_objects' => []]);

        $judgment = $prophet->judge('/x.php', <<<'PHP'
        <?php
        namespace App;
        class Worker {
            public function run(callable | null $shouldExit = null): void {
                $shouldExit ??= static fn () => false;
                $shouldExit();
            }
        }
        PHP);

        $this->assertCount(1, $judgment->sins);
        $this->assertStringNotContainsString('AUTO-FIXABLE', $judgment->sins[0]->message);
        $this->assertStringContainsString('null_objects', $judgment->sins[0]->suggestion);
    }

    // ────────────────────────────────────────────────────────────────
    // Pattern A3 — runtime RHS, silent
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_runtime_resolution_rhs(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function run(Service | null $service = null): void {
                $service ??= $this->resolveService();
                $service->go();
            }
            private function resolveService(): mixed { return null; }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous(), 'Runtime RHS should be silent.');
    }

    // ────────────────────────────────────────────────────────────────
    // Pattern A — edge cases that must NOT fire
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_when_normalization_is_not_first(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function run(Observer | null $observer = null): void {
                $this->prepare();
                $observer ??= new NullObserver;
            }
            private function prepare(): void {}
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_when_param_reassigned_to_null(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function run(Observer | null $observer = null): void {
                $observer ??= new NullObserver;
                if ($this->shouldDrop()) {
                    $observer = null;
                }
            }
            private function shouldDrop(): bool { return false; }
        }
        PHP);

        $this->assertCount(0, $judgment->sins);
    }

    public function test_does_not_flag_when_func_get_args_present(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function run(Observer | null $observer = null): void {
                $observer ??= new NullObserver;
                $args = func_get_args();
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_param_without_null_default(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function run(Observer | null $observer): void {
                $observer ??= new NullObserver;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_param_with_wide_union(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function run(string | int | null $x = null): void {
                $x ??= 'default';
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_non_nullable_param_with_default(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function run(Observer $observer = new NullObserver): void {
                $observer->executing();
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Pattern B — warnings for repeated ?-> chains
    // ────────────────────────────────────────────────────────────────

    public function test_warns_on_repeated_nullsafe_property_use(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class ImageHasher {
            public function __construct(
                private readonly LoggerInterface | null $logger = null,
            ) {}
            public function hash(string $url): string {
                $this->logger?->info('starting');
                $work = $url;
                $this->logger?->info('done');
                return $work;
            }
        }
        PHP);

        $this->assertCount(0, $judgment->sins);
        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('logger', $judgment->warnings[0]->message);
        $this->assertStringContainsString('new NullLogger', $judgment->warnings[0]->message);
    }

    public function test_warns_on_repeated_nullsafe_method_call_on_param(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function step(Observer | null $observer = null, mixed $cmd = null): void {
                $observer?->executing($cmd);
                $this->execute($cmd);
                $observer?->completed($cmd);
            }
            private function execute(mixed $cmd): void {}
        }
        PHP);

        // Pattern A doesn't fire (no normalization) so only the warning.
        $this->assertCount(0, $judgment->sins);
        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_warn_on_single_nullsafe_use(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function getLogo(Shop | null $shop): ?string {
                return $shop?->logoUrl;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_warn_when_explicit_null_branch_present(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function render(Profile | null $profile): string {
                if ($profile === null) {
                    return 'empty';
                }
                $profile?->load();
                $profile?->paint();
                return $profile->name;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_warn_on_datetime_nullable(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function dump(DateTimeImmutable | null $at): void {
                $a = $at?->format('Y');
                $b = $at?->format('m');
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_warn_when_type_not_nullable(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function step(Observer $observer): void {
                $observer?->a();
                $observer?->b();
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_warn_when_param_has_no_default(): void
    {
        // The classic "caller decides explicitly" contract — `T|null $tax`
        // with no default is *the caller's* responsibility to handle, not
        // a soft optional dep on the callee side.
        $judgment = $this->judge(<<<'PHP'
        class Payload {
            public function withTax(TaxData | null $tax): static {
                return $this
                    ->set('tax_class', $tax?->name)
                    ->set('tax_status', $tax?->status);
            }
            private function set(string $k, mixed $v): static { return $this; }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous(), 'Nullable param without `= null` is a contract, not a Null Object candidate.');
    }

    public function test_does_not_warn_when_promoted_property_has_no_default(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function __construct(
                private readonly LoggerInterface | null $logger,
            ) {}
            public function hash(string $url): string {
                $this->logger?->info('a');
                $this->logger?->info('b');
                return $url;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_warns_when_promoted_property_has_explicit_null_default(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function __construct(
                private readonly LoggerInterface | null $logger = null,
            ) {}
            public function hash(string $url): string {
                $this->logger?->info('a');
                $this->logger?->info('b');
                return $url;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_warn_when_class_property_has_no_default(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            private LoggerInterface | null $logger;
            public function __construct(LoggerInterface | null $logger) {
                $this->logger = $logger;
            }
            public function hash(string $url): string {
                $this->logger?->info('a');
                $this->logger?->info('b');
                return $url;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_warns_when_class_property_has_explicit_null_default(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            private LoggerInterface | null $logger = null;
            public function hash(string $url): string {
                $this->logger?->info('a');
                $this->logger?->info('b');
                return $url;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    // ────────────────────────────────────────────────────────────────
    // Mixed sins + warnings in one judgment
    // ────────────────────────────────────────────────────────────────

    public function test_emits_both_sins_and_warnings_in_one_judgment(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Worker {
            public function __construct(
                private readonly LoggerInterface | null $logger = null,
            ) {}
            public function run(Observer | null $observer = null): void {
                $observer ??= new NullObserver;
                $this->logger?->info('a');
                $this->logger?->info('b');
                $observer->executing();
            }
        }
        PHP);

        $this->assertCount(1, $judgment->sins);
        $this->assertCount(1, $judgment->warnings);
        $this->assertTrue($judgment->isFallen());
        $this->assertTrue($judgment->hasWarnings());
    }

    // ────────────────────────────────────────────────────────────────
    // Auto-fix
    // ────────────────────────────────────────────────────────────────

    public function test_repent_hoists_constant_default_and_drops_normalization(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class Worker {
            public function run(Observer | null $observer = null): void {
                $observer ??= new NullObserver;
                $observer->executing();
            }
        }
        PHP;

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('Observer $observer = new NullObserver', $result->newContent);
        $this->assertStringNotContainsString('??=', $result->newContent);
    }

    public function test_repent_uses_mapped_null_object_for_closure_default(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class Worker {
            public function run(callable | null $shouldExit = null): void {
                $shouldExit ??= static fn () => false;
                $shouldExit();
            }
        }
        PHP;

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('callable $shouldExit = new NullCallable', $result->newContent);
        $this->assertStringNotContainsString('static fn', $result->newContent);
    }

    public function test_repent_leaves_unmapped_closures_alone(): void
    {
        $prophet = new PreferNullObjectDefaultsProphet;
        $prophet->configure(['null_objects' => []]);

        $content = <<<'PHP'
        <?php
        namespace App;
        class Worker {
            public function run(callable | null $shouldExit = null): void {
                $shouldExit ??= static fn () => false;
            }
        }
        PHP;

        $result = $prophet->repent('/x.php', $content);

        $this->assertFalse($result->absolved);
    }

    public function test_repent_leaves_runtime_resolution_alone(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class Worker {
            public function run(Svc | null $svc = null): void {
                $svc ??= $this->resolve();
            }
            private function resolve(): mixed { return null; }
        }
        PHP;

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertFalse($result->absolved);
    }

    // ────────────────────────────────────────────────────────────────
    // Robustness
    // ────────────────────────────────────────────────────────────────

    public function test_empty_file_is_righteous(): void
    {
        $this->assertTrue($this->prophet->judge('/x.php', '<?php')->isRighteous());
    }

    public function test_invalid_syntax_is_righteous(): void
    {
        $this->assertTrue($this->prophet->judge('/x.php', '<?php this is not valid <<<')->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }

    private function judge(string $body): Judgment
    {
        $content = "<?php\nnamespace App;\n" . $body;

        return $this->prophet->judge('/x.php', $content);
    }
}
