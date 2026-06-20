<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\OutOfPurposeProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use PHPUnit\Framework\TestCase;

class OutOfPurposeProphetTest extends TestCase
{
    private OutOfPurposeProphet $prophet;

    protected function setUp(): void
    {
        $this->prophet = new OutOfPurposeProphet();
    }

    // -------- verified positives (the issue's known-bad shapes) --------

    public function test_flags_a_registry_that_imports_reflection_class(): void
    {
        // #134: DefinitionRegistry — a registry that is really a reflection compiler.
        $judgment = $this->judge(<<<'PHP'
use ReflectionClass;

class DefinitionRegistry
{
    private array $definitions = [];
    public function register(string $k, $v): void { $this->definitions[$k] = $v; }
    public function get(string $k) { return $this->definitions[$k]; }
    private function reflect(string $class): array { return (new ReflectionClass($class))->getMethods(); }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('out-of-purpose:registry:DefinitionRegistry', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('ReflectionClass', $judgment->warnings[0]->message);
    }

    public function test_flags_a_registry_that_uses_structure_discoverer(): void
    {
        // The Spatie discoverer is an OPTIONAL forbidden default — config-overridable.
        $judgment = $this->judge(<<<'PHP'
use Spatie\StructureDiscoverer\Discover;

class NodeDescriptorRegistry
{
    private array $descriptors = [];
    public function register(string $k, $v): void { $this->descriptors[$k] = $v; }
    public function get(string $k) { return $this->descriptors[$k]; }
    private function discover(): array { return Discover::in('src')->get(); }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('out-of-purpose:registry:NodeDescriptorRegistry', $judgment->warnings[0]->symbol);
    }

    public function test_flags_a_registry_that_uses_reflection_unqualified_inline(): void
    {
        // Caught even when not imported — via the `new ReflectionClass` reference.
        $judgment = $this->judge(<<<'PHP'
class ThingRegistry
{
    private array $things = [];
    public function register(string $k, $v): void { $this->things[$k] = $v; }
    public function get(string $k) { return $this->things[$k]; }
    private function reflect(string $class): array { return (new \ReflectionClass($class))->getMethods(); }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('out-of-purpose:registry:ThingRegistry', $judgment->warnings[0]->symbol);
    }

    public function test_flags_a_registry_that_uses_any_reflection_family_class(): void
    {
        // GENERIC: the whole native `Reflection*` family is caught, not a list —
        // ReflectionEnum here, never enumerated in config.
        $judgment = $this->judge(<<<'PHP'
use ReflectionEnum;

class CaseRegistry
{
    private array $cases = [];
    public function register(string $k, $v): void { $this->cases[$k] = $v; }
    public function get(string $k) { return $this->cases[$k]; }
    private function reflect(string $enum): array { return (new ReflectionEnum($enum))->getCases(); }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('out-of-purpose:registry:CaseRegistry', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('ReflectionEnum', $judgment->warnings[0]->message);
    }

    public function test_flags_a_data_dto_with_an_assembler_cluster(): void
    {
        // A DTO that is a genuine assembler — a cluster of builder methods.
        $judgment = $this->judge(<<<'PHP'
use Spatie\LaravelData\Data;

class OutcomeData extends Data
{
    public string $id;
    public function buildSummary(): array { return []; }
    public function assembleReport(): array { return []; }
    public function hydrateFrom(array $raw): self { return $this; }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('out-of-purpose:data:OutcomeData', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('assembler cluster', $judgment->warnings[0]->message);
    }

    public function test_flags_a_data_dto_that_injects_a_service_to_assemble_itself(): void
    {
        // A non-readonly service dep AND at least one builder method = an assembler.
        $judgment = $this->judge(<<<'PHP'
use Spatie\LaravelData\Data;

class TestRunOutcomeData extends Data
{
    public function __construct(
        public readonly string $id,
        private NodeDescriptorRegistry $registry,
    ) {}

    public function buildFromRegistry(): array { return $this->registry->all(); }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('out-of-purpose:data:TestRunOutcomeData', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('NodeDescriptorRegistry', $judgment->warnings[0]->message);
    }

    public function test_flags_a_resolver_that_owns_a_registration_store(): void
    {
        // A *Resolver with register() + a keyed-array store IS a registry.
        $judgment = $this->judge(<<<'PHP'
class StrategyResolver
{
    private array $strategies = [];
    public function register(string $k, $v): void { $this->strategies[$k] = $v; }
    public function get(string $k) { return $this->strategies[$k]; }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('out-of-purpose:resolver:StrategyResolver', $judgment->warnings[0]->symbol);
    }

    public function test_flags_a_registry_via_secondary_verb_cluster_signal(): void
    {
        // No forbidden import, but a marked registry whose methods are two foreign
        // verb engines (reflect* + hydrate*) — a second job by spread.
        $judgment = $this->judge(<<<'PHP'
class CompilerRegistry
{
    private array $items = [];
    public function register(string $k, $v): void { $this->items[$k] = $v; }
    public function get(string $k) { return $this->items[$k]; }
    private function reflectOne($x): void {}
    private function reflectAll($x): void {}
    private function reflectInto($x): void {}
    private function hydrateOne($x): void {}
    private function hydrateMany($x): void {}
    private function hydrateInto($x): void {}
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('out-of-purpose:registry:CompilerRegistry', $judgment->warnings[0]->symbol);
    }

    // -------- GENERIC positives (plain PHP — no Laravel/Spatie) --------

    public function test_flags_a_plain_php_registry_using_reflection(): void
    {
        // No framework whatsoever — a vanilla *Registry doing reflection fires on
        // the generic `Reflection*` backbone alone.
        $judgment = $this->judge(<<<'PHP'
use ReflectionClass;

class HandlerRegistry
{
    private array $handlers = [];
    public function register(string $name, object $handler): void { $this->handlers[$name] = $handler; }
    public function get(string $name): object { return $this->handlers[$name]; }
    private function describe(object $handler): array { return (new ReflectionClass($handler))->getMethods(); }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('out-of-purpose:registry:HandlerRegistry', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('ReflectionClass', $judgment->warnings[0]->message);
    }

    public function test_flags_a_plain_php_resolver_with_a_register_store(): void
    {
        // No framework — a vanilla *Resolver that owns a register-store IS a registry.
        $judgment = $this->judge(<<<'PHP'
class FormatterResolver
{
    private array $formatters = [];
    public function register(string $type, callable $fn): void { $this->formatters[$type] = $fn; }
    public function resolve(string $type): callable { return $this->formatters[$type]; }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('out-of-purpose:resolver:FormatterResolver', $judgment->warnings[0]->symbol);
    }

    // -------- FP corpus (must stay silent) --------

    public function test_does_not_flag_a_service_provider_with_a_big_binding_list(): void
    {
        $judgment = $this->judge(<<<'PHP'
use ReflectionClass;
use Illuminate\Database\Eloquent\Model;

class WorkflowsServiceProvider extends ServiceProvider
{
    private array $bindings = [];
    public function register(): void { $this->app->singleton('a', fn () => 1); }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_fluent_builder_dsl(): void
    {
        // A Pipeline-like DSL: builder IS its executor; methods return self/$this.
        $judgment = $this->judge(<<<'PHP'
class Pipeline
{
    private array $pipes = [];
    public function send($x): self { return $this; }
    public function through(array $pipes): self { $this->pipes = $pipes; return $this; }
    public function then(callable $fn): self { return $this; }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_cohesive_registry_with_no_forbidden_collaborator(): void
    {
        // Only its own verb vocabulary (find*/register*) — no foreign engine, no import.
        $judgment = $this->judge(<<<'PHP'
class ChannelRegistry
{
    private array $channels = [];
    public function register(string $k, $v): void { $this->channels[$k] = $v; }
    public function registerMany(array $items): void {}
    public function findByName(string $n) { return $this->channels[$n] ?? null; }
    public function findById(int $id) { return null; }
    public function all(): array { return $this->channels; }
    public function has(string $k): bool { return isset($this->channels[$k]); }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_plain_registry_that_only_stores_and_looks_up(): void
    {
        $judgment = $this->judge(<<<'PHP'
class TemplateRegistry
{
    private array $templates = [];
    public function register(string $k, $v): void { $this->templates[$k] = $v; }
    public function get(string $k) { return $this->templates[$k]; }
    public function has(string $k): bool { return isset($this->templates[$k]); }
    public function all(): array { return $this->templates; }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_pure_payload_data_dto(): void
    {
        $judgment = $this->judge(<<<'PHP'
use Spatie\LaravelData\Data;

class InputSocketData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly int $arity,
        public readonly PortData $port,
    ) {}
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_data_dto_that_merely_references_one_service(): void
    {
        // A DTO that holds a single (readonly) service reference and returns a type
        // is NOT an assembler — the over-fire the re-tune fixes (53 hits in one
        // consumer). No builder cluster → quiet.
        $judgment = $this->judge(<<<'PHP'
use Spatie\LaravelData\Data;

class ReportData extends Data
{
    public function __construct(
        public readonly string $title,
        public readonly ChannelRegistry $registry,
    ) {}

    public function registry(): ChannelRegistry { return $this->registry; }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_data_dto_with_a_lone_builder_method(): void
    {
        // One build* method is the universal grammar of construction, not an
        // assembler ENGINE — below the cluster threshold → quiet.
        $judgment = $this->judge(<<<'PHP'
use Spatie\LaravelData\Data;

class WidgetData extends Data
{
    public string $id;
    public function buildView(): array { return ['id' => $this->id]; }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_data_dto_that_uses_reflection_without_a_cluster(): void
    {
        // Reflection in a DTO no longer fires by itself — only a genuine assembler
        // cluster (or a service dep + builder) does. A single reflect() helper is quiet.
        $judgment = $this->judge(<<<'PHP'
use Spatie\LaravelData\Data;
use ReflectionClass;

class MetaData extends Data
{
    public string $id;
    private function reflect(): array { return (new ReflectionClass($this))->getMethods(); }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_class_with_no_role_marker(): void
    {
        // Imports ReflectionClass and discovers — but no role to be incoherent with.
        $judgment = $this->judge(<<<'PHP'
use ReflectionClass;
use Spatie\StructureDiscoverer\Discover;

class SchemaCompiler
{
    public function compile(string $class): array { return (new ReflectionClass($class))->getMethods(); }
    public function discover(): array { return Discover::in('src')->get(); }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_focused_pipe_that_uses_reflection(): void
    {
        // GENERIC FP: a focused, single-purpose *Pipe whose handle() step IS
        // reflection — a static-analysis pipe. There is no `pipe` role, and even a
        // pipe-named class with no other role marker is never incoherent. (This is
        // the package's own `src/Support/Pipes/Php/*` shape — must stay silent.)
        $judgment = $this->judge(<<<'PHP'
use ReflectionClass;

class TypeCheckerPipe
{
    public function handle(string $fqcn): bool
    {
        return (new ReflectionClass($fqcn))->isEnum();
    }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_resolver_that_only_derives_on_demand(): void
    {
        // No store — it computes; that is exactly a resolver's job.
        $judgment = $this->judge(<<<'PHP'
class TypeResolver
{
    public function resolve(string $type): object { return new \stdClass(); }
    public function resolveFor(object $ctx): object { return new \stdClass(); }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_cohesive_single_purpose_class(): void
    {
        // GENERIC FP: a plain, cohesive single-purpose class with no role marker —
        // nothing to be incoherent with, no framework anywhere.
        $judgment = $this->judge(<<<'PHP'
class MoneyFormatter
{
    public function format(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2) . ' ' . $currency;
    }

    public function parse(string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_marked_class_opted_out_by_attribute(): void
    {
        $judgment = $this->judge(<<<'PHP'
use ReflectionClass;

#[OutOfPurposeExempt]
class DefinitionRegistry
{
    private array $definitions = [];
    public function register(string $k, $v): void { $this->definitions[$k] = $v; }
    public function get(string $k) { return $this->definitions[$k]; }
    private function reflect(string $class): array { return (new ReflectionClass($class))->getMethods(); }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_an_abstract_role_base(): void
    {
        $judgment = $this->judge(<<<'PHP'
use ReflectionClass;

abstract class Registry
{
    protected array $items = [];
    public function register(string $k, $v): void { $this->items[$k] = $v; }
    abstract protected function reflect(string $class): array;
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    // -------- config --------

    public function test_respects_a_custom_role_catalog(): void
    {
        $prophet = new OutOfPurposeProphet();
        $prophet->configure([
            'roles' => [
                'gateway' => [
                    'markers' => ['suffix' => ['Gateway']],
                    'forbidden' => ['DOMDocument'],
                    'forbidden_namespaces' => [],
                    'verbs' => ['charge', 'capture', 'refund'],
                    'second_job' => 'parsing',
                    'cut' => 'Extract it.',
                ],
            ],
        ]);

        $judgment = $prophet->judge('/x.php', <<<'PHP'
<?php

namespace App;

use DOMDocument;

class PaymentGateway
{
    public function charge(): void { (new DOMDocument())->loadHTML('<x/>'); }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('out-of-purpose:gateway:PaymentGateway', $judgment->warnings[0]->symbol);
    }

    public function test_generic_reflection_backbone_survives_emptied_forbidden_lists(): void
    {
        // Prove the framework-agnostic backbone: with the role's forbidden +
        // forbidden_namespaces emptied, a *Registry doing reflection STILL fires.
        $prophet = new OutOfPurposeProphet();
        $prophet->configure([
            'roles' => [
                'registry' => [
                    'markers' => ['suffix' => ['Registry']],
                    'forbidden' => [],
                    'forbidden_namespaces' => [],
                    'verbs' => ['register', 'get', 'has', 'all'],
                    'second_job' => 'reflection',
                    'cut' => 'Extract a *Reflector.',
                ],
            ],
        ]);

        $judgment = $prophet->judge('/x.php', <<<'PHP'
<?php

namespace App;

use ReflectionClass;

class WidgetRegistry
{
    private array $widgets = [];
    public function register(string $k, $v): void { $this->widgets[$k] = $v; }
    public function get(string $k) { return $this->widgets[$k]; }
    private function reflect(string $class): array { return (new ReflectionClass($class))->getMethods(); }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('out-of-purpose:registry:WidgetRegistry', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('ReflectionClass', $judgment->warnings[0]->message);
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n\nnamespace App;\n\n{$body}\n");
    }
}
