<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Hints\DataHintScribe;
use JesseGall\CodeCommandments\Cli\Hints\Hints;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use PHPUnit\Framework\TestCase;

/**
 * Real end-to-end tests for the `hints` rewriter: each test writes actual PHP files
 * into a throwaway temp project, runs the command so it rewrites them ON DISK, then
 * asserts the file contents. Every temp project is removed in tearDown.
 */
final class HintsTest extends TestCase
{
    /** @var list<string> */
    private array $projects = [];

    private const string DATA_STUB = "<?php\nnamespace Spatie\\LaravelData { class Data {} }\n";

    protected function tearDown(): void
    {
        foreach ($this->projects as $dir) {
            $this->deleteDir($dir);
        }

        $this->projects = [];
    }

    public function test_does_not_rename_a_multi_parameter_named_constructor(): void
    {
        // A factory with more than one parameter is a named constructor, not a `from()` target
        // (from dispatches by ONE argument's type). It — and its call sites — must be untouched.
        $dir = $this->project([
            'OutputSocketData.php' => <<<'PHP'
                <?php
                namespace Demo;
                use Spatie\LaravelData\Data;

                final class OutputSocketData extends Data
                {
                    public function __construct(public readonly string $name, public readonly int $size) {}

                    public static function make(string $name, int $size): self
                    {
                        return new self($name, $size);
                    }
                }
                PHP,
            'Builder.php' => <<<'PHP'
                <?php
                namespace Demo;
                class Builder
                {
                    public function build()
                    {
                        return OutputSocketData::make(name: 'result', size: 3);
                    }
                }
                PHP,
        ]);

        $this->apply($dir);

        $data = $this->read($dir, 'OutputSocketData.php');
        $builder = $this->read($dir, 'Builder.php');

        // The multi-arg factory keeps its name, its call site, and gets no `@method from` hint.
        $this->assertStringContainsString('public static function make(string $name, int $size)', $data);
        $this->assertStringContainsString("OutputSocketData::make(name: 'result', size: 3)", $builder);
        $this->assertStringNotContainsString('@method static static from(', $data);
    }

    public function test_strips_a_named_argument_from_a_single_param_factory_call(): void
    {
        // A single-param factory IS a `from()` target — a named call can't ride on
        // `from(credential: $c)`, so the name is dropped to dispatch positionally: `from($c)`.
        $dir = $this->project([
            'CredentialData.php' => <<<'PHP'
                <?php
                namespace Demo;
                use Spatie\LaravelData\Data;
                use Demo\Models\Credential;

                final class CredentialData extends Data
                {
                    public function __construct(public readonly string $id) {}

                    public static function forCredential(Credential $credential): self
                    {
                        return self::from(['id' => $credential->id]);
                    }
                }
                PHP,
            'Caller.php' => <<<'PHP'
                <?php
                namespace Demo;
                class Caller
                {
                    public function show($credential)
                    {
                        return CredentialData::forCredential(credential: $credential);
                    }
                }
                PHP,
        ]);

        $this->apply($dir);

        $caller = $this->read($dir, 'Caller.php');

        $this->assertStringContainsString('CredentialData::from($credential)', $caller);
        $this->assertStringNotContainsString('credential:', $caller);
    }

    public function test_renames_non_from_factory_rewrites_call_sites_and_fixes_the_method_tag(): void
    {
        $dir = $this->project([
            'CredentialData.php' => <<<'PHP'
                <?php
                namespace Demo;
                use Spatie\LaravelData\Data;
                use Demo\Models\Credential;

                /**
                 * The view of a stored credential.
                 *
                 * @method static static forCredential(Credential $credential)
                 */
                final class CredentialData extends Data
                {
                    public function __construct(public readonly string $id) {}

                    public static function forCredential(Credential $credential): self
                    {
                        return self::from(['id' => $credential->id]);
                    }
                }
                PHP,
            'Caller.php' => <<<'PHP'
                <?php
                namespace Demo;
                class Caller
                {
                    public function show($credential)
                    {
                        return CredentialData::forCredential($credential);
                    }
                }
                PHP,
        ]);

        $this->apply($dir);

        $data = $this->read($dir, 'CredentialData.php');
        $caller = $this->read($dir, 'Caller.php');

        // factory renamed to a from-prefixed name…
        $this->assertStringContainsString('public static function fromCredential(Credential $credential): self', $data);
        // …the @method documents the magic `from`, not the concrete name…
        $this->assertStringContainsString('@method static static from(Credential $credential)', $data);
        // …the collision name is gone entirely…
        $this->assertStringNotContainsString('forCredential', $data);
        // …and call sites dispatch through ::from().
        $this->assertStringContainsString('CredentialData::from($credential)', $caller);
        $this->assertStringNotContainsString('forCredential', $caller);
    }

    public function test_documents_a_from_prefixed_factory_without_renaming_it(): void
    {
        $dir = $this->project([
            'AgentData.php' => <<<'PHP'
                <?php
                namespace Demo;
                use Spatie\LaravelData\Data;
                use Demo\Models\Agent;

                final class AgentData extends Data
                {
                    public function __construct(public readonly int $id) {}

                    public static function fromModel(Agent $agent): self
                    {
                        return self::from(['id' => $agent->id]);
                    }
                }
                PHP,
            'Caller.php' => <<<'PHP'
                <?php
                namespace Demo;
                class Caller
                {
                    public function show($agent)
                    {
                        return AgentData::fromModel($agent);
                    }
                }
                PHP,
        ]);

        $this->apply($dir);

        $data = $this->read($dir, 'AgentData.php');

        // a from* factory is left named as-is, only documented…
        $this->assertStringContainsString('public static function fromModel(Agent $agent): self', $data);
        $this->assertStringContainsString('@method static static from(Agent $agent)', $data);
        // …and its call sites are not touched.
        $this->assertStringContainsString('AgentData::fromModel($agent)', $this->read($dir, 'Caller.php'));
    }

    public function test_adds_the_conditional_collect_hint_when_the_class_is_collected(): void
    {
        $dir = $this->project([
            'RowData.php' => <<<'PHP'
                <?php
                namespace Demo;
                use Spatie\LaravelData\Data;

                final class RowData extends Data
                {
                    public function __construct(public readonly string $value) {}
                }
                PHP,
            'Importer.php' => <<<'PHP'
                <?php
                namespace Demo;
                class Importer
                {
                    public function rows(array $rows): array
                    {
                        return RowData::collect($rows);
                    }
                }
                PHP,
        ]);

        $this->apply($dir);

        $this->assertStringContainsString(
            'collect(iterable $items)',
            $this->read($dir, 'RowData.php'),
        );
        $this->assertStringContainsString('$items is \Illuminate\Support\Collection', $this->read($dir, 'RowData.php'));
    }

    public function test_synthesises_a_docblock_when_the_class_has_none(): void
    {
        $dir = $this->project([
            'TagData.php' => <<<'PHP'
                <?php
                namespace Demo;
                use Spatie\LaravelData\Data;
                use Demo\Models\Tag;

                final class TagData extends Data
                {
                    public function __construct(public readonly string $label) {}

                    public static function make(Tag $tag): self
                    {
                        return new self($tag->label);
                    }
                }
                PHP,
        ]);

        $this->apply($dir);

        $data = $this->read($dir, 'TagData.php');

        $this->assertStringContainsString('@method static static from(Tag $tag)', $data);
        $this->assertStringContainsString('public static function fromTag(Tag $tag): self', $data);
    }

    public function test_dry_run_writes_nothing_and_prints_a_diff(): void
    {
        $dir = $this->project([
            'CredentialData.php' => <<<'PHP'
                <?php
                namespace Demo;
                use Spatie\LaravelData\Data;
                use Demo\Models\Credential;

                final class CredentialData extends Data
                {
                    public function __construct(public readonly string $id) {}

                    public static function forCredential(Credential $credential): self
                    {
                        return self::from(['id' => $credential->id]);
                    }
                }
                PHP,
        ]);

        $before = $this->read($dir, 'CredentialData.php');
        $diffFile = $dir . '/changes.diff';

        ob_start();
        $code = new Hints()->run([$dir, '--dry-run=' . $diffFile]);
        ob_get_clean();

        $diff = (string) file_get_contents($diffFile);

        $this->assertSame(0, $code);
        $this->assertSame($before, $this->read($dir, 'CredentialData.php'), 'dry-run must not touch the file');
        $this->assertStringContainsString('-    public static function forCredential', $diff);
        $this->assertStringContainsString('+    public static function fromCredential', $diff);
    }

    public function test_leaves_non_data_classes_alone(): void
    {
        $dir = $this->project([
            'Widget.php' => <<<'PHP'
                <?php
                namespace Demo;
                class Widget
                {
                    public static function ofSize(int $n): self
                    {
                        return new self;
                    }
                }
                PHP,
        ]);

        $before = $this->read($dir, 'Widget.php');

        ob_start();
        new Hints()->run([$dir]);
        ob_get_clean();

        $this->assertSame($before, $this->read($dir, 'Widget.php'));
    }

    public function test_scoped_run_is_docblock_only_restricted_to_the_given_files(): void
    {
        $dir = $this->project([
            'OrderData.php' => <<<'PHP'
                <?php
                namespace Demo;
                use Spatie\LaravelData\Data;
                use Demo\Models\Order;
                use Demo\Models\Credential;

                final class OrderData extends Data
                {
                    public function __construct(public readonly int $id) {}

                    public static function fromModel(Order $order): self
                    {
                        return self::from(['id' => $order->id]);
                    }

                    public static function forCredential(Credential $credential): self
                    {
                        return self::from(['id' => $credential->id]);
                    }
                }
                PHP,
            'TagData.php' => <<<'PHP'
                <?php
                namespace Demo;
                use Spatie\LaravelData\Data;
                use Demo\Models\Tag;

                final class TagData extends Data
                {
                    public function __construct(public readonly string $label) {}

                    public static function ofTag(Tag $tag): self
                    {
                        return new self($tag->label);
                    }
                }
                PHP,
        ]);

        // Only OrderData.php is "changed" → docblock-only, scoped to it.
        $changes = new DataHintScribe()->rewrites(
            Codebase::scan($dir),
            Scope::restrictedTo([$dir . '/OrderData.php']),
        );

        // The out-of-scope file is never rewritten.
        $this->assertArrayNotHasKey($dir . '/TagData.php', $changes);

        $order = $changes[$dir . '/OrderData.php'] ?? '';
        // documents the dispatchable from* factory…
        $this->assertStringContainsString('@method static static from(Order $order)', $order);
        // …does NOT rename the non-from factory (no whole-tree call-site visibility)…
        $this->assertStringContainsString('public static function forCredential(Credential $credential)', $order);
        $this->assertStringNotContainsString('fromCredential', $order);
        // …and does NOT document the non-dispatchable one.
        $this->assertStringNotContainsString('from(Credential $credential)', $order);
    }

    /**
     * Write the given files (plus the Spatie Data stub) into a fresh temp project.
     *
     * @param  array<string, string>  $files
     */
    private function project(array $files): string
    {
        $dir = sys_get_temp_dir() . '/cc-hints-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $this->projects[] = $dir;

        file_put_contents($dir . '/Spatie.php', self::DATA_STUB);

        foreach ($files as $name => $contents) {
            file_put_contents($dir . '/' . $name, $contents . "\n");
        }

        return $dir;
    }

    private function apply(string $dir): void
    {
        ob_start();
        $code = new Hints()->run([$dir]);
        ob_get_clean();

        $this->assertSame(0, $code);

        // The rewrite must never produce invalid PHP.
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $status);
            $this->assertSame(0, $status, "rewritten file does not parse: {$file}\n" . implode("\n", $out));
        }
    }

    private function read(string $dir, string $name): string
    {
        return (string) file_get_contents($dir . '/' . $name);
    }

    private function deleteDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->deleteDir($file) : @unlink($file);
        }

        @rmdir($dir);
    }
}
