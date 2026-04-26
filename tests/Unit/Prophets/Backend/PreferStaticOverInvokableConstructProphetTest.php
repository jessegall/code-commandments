<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferStaticOverInvokableConstructProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferStaticOverInvokableConstructProphetTest extends TestCase
{
    private PreferStaticOverInvokableConstructProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferStaticOverInvokableConstructProphet();
    }

    // ────────────────────────────────────────────────────────────────
    // Sins — `(new X(...))()` (no invocation args)
    // ────────────────────────────────────────────────────────────────

    public function test_flags_invokable_construct_without_args_as_sin(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class UserPermissionCache {
            public function __construct(public int $id) {}
            public function __invoke(): mixed { return null; }
        }
        class Caller {
            public function run(int $userId): mixed {
                return (new UserPermissionCache($userId))();
            }
        }
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertCount(0, $judgment->warnings);
        $this->assertStringContainsString('UserPermissionCache', $judgment->sins[0]->message);
    }

    public function test_flags_even_when_class_has_no_static_helper(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Plain {
            public function __construct(public int $id) {}
            public function __invoke(): mixed { return null; }
        }
        class Caller {
            public function run(int $id): mixed { return (new Plain($id))(); }
        }
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('static factory', $judgment->sins[0]->suggestion);
    }

    public function test_sin_suggestion_names_existing_static_helper(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Cache {
            public static function for(int $id): mixed { return null; }
            public function __construct(public int $id) {}
            public function __invoke(): mixed { return null; }
        }
        class Caller {
            public function run(int $id): mixed { return (new Cache($id))(); }
        }
        PHP);

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Cache::for()', $judgment->sins[0]->suggestion);
    }

    // ────────────────────────────────────────────────────────────────
    // Warnings — `(new X(...))(arg, ...)` (has invocation args)
    // ────────────────────────────────────────────────────────────────

    public function test_flags_invokable_construct_with_args_as_warning(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class UserPermissionCache {
            public function __construct(public int $id) {}
            public function __invoke(mixed $key = null): void {}
        }
        class Caller {
            public function clear(int $userId): void {
                (new UserPermissionCache($userId))(null);
            }
        }
        PHP);

        $this->assertFalse($judgment->isFallen());
        $this->assertTrue($judgment->hasWarnings());
        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('UserPermissionCache', $judgment->warnings[0]->message);
    }

    public function test_warning_mentions_existing_static_helper_when_present(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Cache {
            public static function forget(int $id): void {}
            public function __construct(public int $id) {}
            public function __invoke(mixed $key = null): void {}
        }
        class Caller {
            public function clear(int $id): void { (new Cache($id))(null); }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Cache::forget()', $judgment->warnings[0]->message);
    }

    // ────────────────────────────────────────────────────────────────
    // Mixed sins + warnings in one file
    // ────────────────────────────────────────────────────────────────

    public function test_handles_sin_and_warning_in_same_file(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Cache {
            public function __construct(public int $id) {}
            public function __invoke(mixed $key = null): mixed { return null; }
        }
        class Caller {
            public function read(int $id): mixed { return (new Cache($id))(); }
            public function clear(int $id): void { (new Cache($id))(null); }
        }
        PHP);

        $this->assertCount(1, $judgment->sins);
        $this->assertCount(1, $judgment->warnings);
    }

    // ────────────────────────────────────────────────────────────────
    // Vendor classes — must not flag
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_target_class_in_vendor(): void
    {
        // PhpParser\ParserFactory ships with this package's vendor dir, so
        // the prophet should treat it as out of scope.
        $judgment = $this->judge(<<<'PHP'
        class Caller {
            public function run(): mixed {
                return (new \PhpParser\ParserFactory())();
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous(), 'Vendor classes must not be flagged.');
    }

    public function test_does_not_flag_when_judged_file_lives_in_vendor(): void
    {
        // Edge case: when the file being judged is itself in vendor
        // (e.g. user passes --path=vendor/foo), classes defined locally
        // in that file are also vendor and should be skipped.
        $content = <<<'PHP'
        <?php
        namespace App;
        class Cache {
            public function __construct(public int $id) {}
            public function __invoke(): mixed { return null; }
        }
        class Caller {
            public function run(int $id): mixed { return (new Cache($id))(); }
        }
        PHP;

        $judgment = $this->prophet->judge('/project/vendor/foo/src/file.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Out of scope — should not flag
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_plain_new_passed_as_callable(): void
    {
        // The construction itself is not invoked.
        $judgment = $this->judge(<<<'PHP'
        class Pipeline {
            public static function through(array $pipes): mixed { return null; }
        }
        class Foo {
            public function __construct() {}
            public function __invoke(): mixed { return null; }
        }
        class Caller {
            public function run(): mixed {
                return Pipeline::through([new Foo()]);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_anonymous_class_invocation(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Caller {
            public function run(): mixed {
                return (new class { public function __invoke(): int { return 1; } })();
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_static_call_directly(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Cache {
            public static function for(int $id): mixed { return null; }
            public function __construct(public int $id) {}
            public function __invoke(): mixed { return null; }
        }
        class Caller {
            public function run(int $id): mixed {
                return Cache::for($id);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Multiple sins / dedupe
    // ────────────────────────────────────────────────────────────────

    public function test_flags_each_distinct_call_site(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Cache {
            public function __construct(public int $id) {}
            public function __invoke(): mixed { return null; }
        }
        class Caller {
            public function a(int $id): mixed { return (new Cache($id))(); }
            public function b(int $id): mixed { return (new Cache($id))(); }
        }
        PHP);

        $this->assertFallen($judgment, 2);
    }

    public function test_dedupes_identical_call_on_same_line(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Cache {
            public function __construct(public int $id) {}
            public function __invoke(): mixed { return null; }
        }
        class Caller {
            public function run(int $id): mixed { return (new Cache($id))(); }
        }
        PHP);

        $this->assertFallen($judgment, 1);
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

    public function test_reports_correct_line_number(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class Cache {
            public function __construct(public int $id) {}
            public function __invoke(): mixed { return null; }
        }
        class Caller {
            public function run(int $id): mixed {
                $x = 1;
                return (new Cache($id))();
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertSame(10, $judgment->sins[0]->line);
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertStringContainsString('static', $this->prophet->description());
    }

    // ────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────

    private function judge(string $body): Judgment
    {
        $content = "<?php\nnamespace App;\n" . $body;

        return $this->prophet->judge('/x.php', $content);
    }

    private function assertFallen(Judgment $judgment, ?int $expected = null): void
    {
        $this->assertTrue(
            $judgment->isFallen(),
            'Expected fallen. Sins: ' . json_encode(array_map(fn ($s) => $s->message, $judgment->sins))
        );

        if ($expected !== null) {
            $this->assertCount(
                $expected,
                $judgment->sins,
                'Sins: ' . json_encode(array_map(fn ($s) => $s->message, $judgment->sins))
            );
        }
    }
}
