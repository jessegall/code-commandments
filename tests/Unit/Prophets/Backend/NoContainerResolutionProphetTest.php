<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoContainerResolutionProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoContainerResolutionProphetTest extends TestCase
{
    private NoContainerResolutionProphet $prophet;
    private ?string $tempDir = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoContainerResolutionProphet();
    }

    protected function tearDown(): void
    {
        if ($this->tempDir !== null && is_dir($this->tempDir)) {
            $this->removeDir($this->tempDir);
            $this->tempDir = null;
        }

        parent::tearDown();
    }

    // ────────────────────────────────────────────────────────────────
    // app(X::class)
    // ────────────────────────────────────────────────────────────────

    public function test_flags_app_helper_with_class_constant(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class CreateInvoice {
            public function handle(): mixed {
                $generator = app(InvoiceGenerator::class);
                return $generator->run();
            }
        }
        PHP);

        $this->assertWarningCount($judgment, 1);
        $this->assertStringContainsString('app(InvoiceGenerator::class)', $judgment->warnings[0]->message);
        $this->assertStringContainsString('inject InvoiceGenerator via the constructor', $judgment->warnings[0]->message);
    }

    public function test_flags_app_helper_with_string_key(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Reader {
            public function read(): mixed {
                return app('config')->get('app.name');
            }
        }
        PHP);

        $this->assertWarningCount($judgment, 1);
        $this->assertStringContainsString("app('config')", $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_app_helper_with_no_arguments(): void
    {
        // app() with no args returns the container; common with ->bind()/->singleton()
        $judgment = $this->judge(<<<'PHP'
        class Wirer {
            public function configure(): void {
                app()->bind('foo', fn () => 'bar');
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
        $this->assertFalse($judgment->hasWarnings());
    }

    // ────────────────────────────────────────────────────────────────
    // resolve(X::class)
    // ────────────────────────────────────────────────────────────────

    public function test_flags_resolve_helper(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Service {
            public function run(): mixed {
                return resolve(PaymentGateway::class)->charge();
            }
        }
        PHP);

        $this->assertWarningCount($judgment, 1);
        $this->assertStringContainsString('resolve(PaymentGateway::class)', $judgment->warnings[0]->message);
    }

    // ────────────────────────────────────────────────────────────────
    // app()->make(X::class)
    // ────────────────────────────────────────────────────────────────

    public function test_flags_app_make_chain(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Service {
            public function run(): mixed {
                return app()->make(PaymentGateway::class)->charge();
            }
        }
        PHP);

        $this->assertWarningCount($judgment, 1);
        $this->assertStringContainsString('app()->make(PaymentGateway::class)', $judgment->warnings[0]->message);
    }

    public function test_flags_app_make_with(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Service {
            public function run(): mixed {
                return app()->makeWith(Mailer::class, ['driver' => 'smtp']);
            }
        }
        PHP);

        $this->assertWarningCount($judgment, 1);
        $this->assertStringContainsString('Mailer', $judgment->warnings[0]->message);
    }

    // ────────────────────────────────────────────────────────────────
    // App::make(X::class)
    // ────────────────────────────────────────────────────────────────

    public function test_flags_app_facade_make_aliased(): void
    {
        $judgment = $this->judge(<<<'PHP'
        use Illuminate\Support\Facades\App;

        class Service {
            public function run(): mixed {
                return App::make(PaymentGateway::class)->charge();
            }
        }
        PHP, withUseStatement: true);

        $this->assertWarningCount($judgment, 1);
        $this->assertStringContainsString('App::make(PaymentGateway::class)', $judgment->warnings[0]->message);
    }

    public function test_flags_app_facade_make_fully_qualified(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Service {
            public function run(): mixed {
                return \Illuminate\Support\Facades\App::make(PaymentGateway::class);
            }
        }
        PHP);

        $this->assertWarningCount($judgment, 1);
    }

    // ────────────────────────────────────────────────────────────────
    // Suggestion mentions origin tracing
    // ────────────────────────────────────────────────────────────────

    public function test_warning_message_mentions_origin_tracing(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Service {
            public function run(): mixed {
                return app(Foo::class);
            }
        }
        PHP);

        $this->assertWarningCount($judgment, 1);
        $message = $judgment->warnings[0]->message;
        $this->assertStringContainsString('new', $message);
        $this->assertStringContainsString('DI-resolved', $message);
    }

    // ────────────────────────────────────────────────────────────────
    // Cross-file origin tracing via CodebaseIndex
    // ────────────────────────────────────────────────────────────────

    public function test_index_proves_containing_class_is_di_resolved(): void
    {
        // Service is never `new`d in the indexed scroll → strong "DI-resolved" wording.
        [$serviceFile] = $this->writeIndexedFiles([
            'Service.php' => <<<'PHP'
            <?php
            namespace App;
            class Service {
                public function run(): mixed { return app(Foo::class); }
            }
            PHP,
            'Caller.php' => <<<'PHP'
            <?php
            namespace App;
            class Caller {
                public function __construct(private Service $service) {}
                public function run(): mixed { return $this->service->run(); }
            }
            PHP,
        ]);

        $judgment = $this->prophet->judge($serviceFile, file_get_contents($serviceFile));

        $this->assertWarningCount($judgment, 1);
        $message = $judgment->warnings[0]->message;
        $this->assertStringContainsString('No `new Service(', $message);
        $this->assertStringContainsString('move the dependency', $message);
    }

    public function test_index_points_at_manual_construction_site(): void
    {
        [$serviceFile] = $this->writeIndexedFiles([
            'Service.php' => <<<'PHP'
            <?php
            namespace App;
            class Service {
                public function run(): mixed { return app(Foo::class); }
            }
            PHP,
            'BadCaller.php' => <<<'PHP'
            <?php
            namespace App;
            class BadCaller {
                public function run(): mixed { return (new Service())->run(); }
            }
            PHP,
        ]);

        $judgment = $this->prophet->judge($serviceFile, file_get_contents($serviceFile));

        $this->assertWarningCount($judgment, 1);
        $message = $judgment->warnings[0]->message;
        $this->assertStringContainsString('`new Service(`', $message);
        $this->assertStringContainsString('BadCaller.php', $message);
    }

    public function test_index_aggregates_count_when_multiple_new_sites(): void
    {
        [$serviceFile] = $this->writeIndexedFiles([
            'Service.php' => <<<'PHP'
            <?php
            namespace App;
            class Service {
                public function run(): mixed { return app(Foo::class); }
            }
            PHP,
            'CallerA.php' => <<<'PHP'
            <?php
            namespace App;
            class CallerA { public function go(): mixed { return (new Service())->run(); } }
            PHP,
            'CallerB.php' => <<<'PHP'
            <?php
            namespace App;
            class CallerB { public function go(): mixed { return (new Service())->run(); } }
            PHP,
        ]);

        $judgment = $this->prophet->judge($serviceFile, file_get_contents($serviceFile));

        $this->assertWarningCount($judgment, 1);
        $this->assertStringContainsString('+1 more', $judgment->warnings[0]->message);
    }

    public function test_index_unknown_containing_class_falls_back_to_outside_scroll_hint(): void
    {
        // Index built from a different class set entirely — Service isn't in it.
        [$serviceFile] = $this->writeIndexedFiles([
            // Subject file (Service) intentionally NOT registered with the index.
            'Other.php' => <<<'PHP'
            <?php
            namespace App;
            class Other {}
            PHP,
        ], registerSubject: false);

        // Subject file added separately and judged.
        $serviceFile = dirname($serviceFile) . '/Service.php';
        file_put_contents($serviceFile, <<<'PHP'
        <?php
        namespace App;
        class Service {
            public function run(): mixed { return app(Foo::class); }
        }
        PHP);

        $judgment = $this->prophet->judge($serviceFile, file_get_contents($serviceFile));

        $this->assertWarningCount($judgment, 1);
        $this->assertStringContainsString('outside the scanned scroll', $judgment->warnings[0]->message);
    }

    public function test_no_index_falls_back_to_manual_verify_hint(): void
    {
        // Whole-file judge with no index → fall back to "verify manually".
        $judgment = $this->judge(<<<'PHP'
        class Service {
            public function run(): mixed { return app(Foo::class); }
        }
        PHP);

        $this->assertWarningCount($judgment, 1);
        $message = $judgment->warnings[0]->message;
        $this->assertStringContainsString('Verify the containing class', $message);
        $this->assertStringContainsString('search for `new Service(`', $message);
    }

    // ────────────────────────────────────────────────────────────────
    // Service providers — must not flag
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_inside_service_provider(): void
    {
        $judgment = $this->judge(<<<'PHP'
        use Illuminate\Support\ServiceProvider;

        class FooServiceProvider extends ServiceProvider {
            public function boot(): void {
                $thing = app(Thing::class);
                resolve(Other::class);
                app()->make(Mailer::class);
                \Illuminate\Support\Facades\App::make(Cache::class);
            }
        }
        PHP, withUseStatement: true);

        $this->assertTrue($judgment->isRighteous());
        $this->assertFalse($judgment->hasWarnings());
    }

    // ────────────────────────────────────────────────────────────────
    // Neutral targets — fetching the container/application itself
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_resolving_application_itself(): void
    {
        $judgment = $this->judge(<<<'PHP'
        use Illuminate\Foundation\Application;

        class Service {
            public function run(): mixed {
                return app(Application::class);
            }
        }
        PHP, withUseStatement: true);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_resolving_container_contract(): void
    {
        $judgment = $this->judge(<<<'PHP'
        use Psr\Container\ContainerInterface;

        class Service {
            public function run(): mixed {
                return resolve(ContainerInterface::class);
            }
        }
        PHP, withUseStatement: true);

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Multiple matches
    // ────────────────────────────────────────────────────────────────

    public function test_flags_each_distinct_call_site(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Service {
            public function run(): mixed {
                $a = app(Foo::class);
                $b = resolve(Bar::class);
                $c = app()->make(Baz::class);
                return [$a, $b, $c];
            }
        }
        PHP);

        $this->assertWarningCount($judgment, 3);
    }

    // ────────────────────────────────────────────────────────────────
    // Out of scope
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_unrelated_function_calls(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Service {
            public function run(): mixed {
                $foo = config('app.name');
                $bar = collect([1, 2, 3])->map(fn ($x) => $x * 2);
                return [$foo, $bar];
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_constructor_injection(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Service {
            public function __construct(private InvoiceGenerator $generator) {}

            public function handle(): mixed {
                return $this->generator->run();
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_method_named_make_on_unrelated_object(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Service {
            public function run(): mixed {
                return $this->factory->make(Widget::class);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
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
        class Service {
            public function run(): mixed {
                $a = 1;
                return app(Foo::class);
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertWarningCount($judgment, 1);
        $this->assertSame(6, $judgment->warnings[0]->line);
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertStringContainsString('constructor injection', $this->prophet->description());
    }

    // ────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────

    private function judge(string $body, bool $withUseStatement = false): Judgment
    {
        $content = "<?php\nnamespace App;\n" . $body;

        return $this->prophet->judge('/x.php', $content);
    }

    /**
     * Write the given files to a temp dir, build a CodebaseIndex from them,
     * inject it into the prophet, and return the absolute paths in the
     * order given. The first file is the "subject" — the one tests will
     * judge directly. Pass `registerSubject: false` to omit that file from
     * the index (used to test the "outside scroll" code path).
     *
     * @param  array<string, string>  $files  filename => php source
     * @return list<string>
     */
    private function writeIndexedFiles(array $files, bool $registerSubject = true): array
    {
        $this->tempDir = realpath(sys_get_temp_dir())
            . '/code-commandments-resolution-' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $paths = [];
        $indexPaths = [];

        foreach ($files as $name => $content) {
            $path = $this->tempDir . '/' . $name;
            file_put_contents($path, $content);
            $paths[] = $path;

            // Skip the subject (first file) when explicitly asked, so the
            // index won't know about its class.
            if (! $registerSubject && count($paths) === 1) {
                continue;
            }

            $indexPaths[] = $path;
        }

        $this->prophet->setCodebaseIndex(CodebaseIndex::build($indexPaths));

        return $paths;
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function assertWarningCount(Judgment $judgment, int $expected): void
    {
        $this->assertTrue(
            $judgment->hasWarnings(),
            'Expected warnings. Got none. Sins: '
                . json_encode(array_map(fn ($s) => $s->message, $judgment->sins))
        );
        $this->assertCount(
            $expected,
            $judgment->warnings,
            'Warnings: ' . json_encode(array_map(fn ($w) => $w->message, $judgment->warnings))
        );
        $this->assertFalse($judgment->isFallen(), 'This prophet must never emit sins.');
    }
}
