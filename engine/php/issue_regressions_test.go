package php_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/detectors/backend"
	backendlaravel "github.com/jessegall/code-commandments/detectors/backend/laravel"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/laravel"
	"github.com/jessegall/code-commandments/engine/php/spatie"
)

func TestCachedPipesAndEloquentModelsResolveAtCallTime(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App\Site;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Foundation\Auth\User as Authenticatable;
interface Pipe {}
#[Singleton]
final class Runner {
    private array $steps = [];
    public function __construct(private Container $container) {}
    /** @param class-string<Pipe> $type */
    public function step(string $type): object {
        return $this->steps[$type] ??= $this->container->make($type);
    }
}
final class CachedPipe implements Pipe {
    public function __construct(private Container $container) {}
    public function run(): string { return $this->container->make(Context::class)->name(); }
}
trait ReadsContext {
    public function read(): object { return Container::getInstance()->make(Context::class); }
}
trait ReadsIdentity { use ReadsContext; }
final class Account extends Authenticatable { use ReadsIdentity; }
final class Link extends MorphPivot { use ReadsContext; }
final class TransientService {
    public function __construct(private Container $container) {}
    public function run(): string { return $this->container->make(Context::class)->name(); }
}
`)
	if found := (backendlaravel.ContainerReachDetector{}).Find(codebase); len(found) != 1 || php.EnclosingClassName(found[0]) != `App\Site\TransientService` {
		t.Fatalf("only the transient service should inject its dependency, found %v", found)
	}
}

func TestWireProjectionsAndSharedOperationsKeepTheirBoundaries(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App\Site;
final readonly class Setting {
    public function __construct(public string $name, public object $operand) {}
    public function stored(): array {
        $operand = $this->operand;
        return ['name' => $this->name, 'operand' => $operand, 'amount' => $operand instanceof Amount ? $operand->number : null];
    }
    public function mixed(object $another): array {
        $operand = $this->operand;
        $operand = $another;
        return ['name' => $this->name, 'operand' => $operand];
    }
}
final class Projection {
    /** @param list<Amount> $amounts */
    public function __construct(private array $amounts, private int $position) {}
    public function current(): ?Amount { return $this->amounts[$this->position] ?? null; }
    public function operations(object $subject): array {
        if ($subject instanceof Amount) {
            $number = $subject->number;
            return [Operation::write($number)];
        }
        if ($subject instanceof Limit) {
            $limit = $subject->limit;
            return [Operation::write($limit)];
        }
        return [];
    }
}
final readonly class Form {
    public function __construct(public int $one, public int $two, public int $three) {}
}
final readonly class Import {
    public function __construct(public int $one, public int $two, public int $three) {}
}
final class SharedWrite {
    public function fromForm(Form $form): void { $this->write($form->one, $form->two, $form->three); }
    public function fromImport(Import $import): void { $this->write($import->one, $import->two, $import->three); }
    public function write(int $first, int $second, int $third): void {}
}
`)
	if projected := codebase.WhereKind("Expr_Array").Where(engine.As(php.Node.ProjectsTypedObject)).Get(); len(projected) != 1 || projected[0].EnclosingFunction().Name() != "stored" {
		t.Fatalf("only the stored shape projects one object, found %v", projected)
	}
	if findings := backend.NullableRegistryLookupDetector.Find(codebase); len(findings) != 0 {
		t.Fatalf("a list cursor can reach the end, found %v", findings)
	}
	if findings := backend.TypeSwitchDetector.Find(codebase); len(findings) != 0 {
		t.Fatalf("the operation projection belongs at its destination, found %v", findings)
	}
	if findings := (backend.DerivedArgumentDetector{}).Find(codebase); len(findings) != 0 {
		t.Fatalf("the shared operation accepts two inputs, found %v", findings)
	}
}

func TestDependencyDeclarationsAreReadWithoutReportingThem(t *testing.T) {
	root := t.TempDir()
	dependency := filepath.Join(root, "vendor", "Library.php")
	if err := os.Mkdir(filepath.Dir(dependency), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(dependency, []byte(`<?php
namespace External\Client;
class Schema { public function asArray(): array { return ['one' => 1]; } }
`), 0o644); err != nil {
		t.Fatal(err)
	}
	app := filepath.Join(root, "App.php")
	if err := os.WriteFile(app, []byte(`<?php
namespace App\Records;
class Consumer extends \External\Client\Schema {
    public function asArray(): array { return parent::asArray(); }
    public function local(): string { return 'still judged'; }
}
`), 0o644); err != nil {
		t.Fatal(err)
	}
	codebase, err := php.Here().Scan(app, dependency)
	if err != nil {
		t.Fatal(err)
	}
	if declarations := codebase.WhereTypeDeclaration().Get(); len(declarations) != 1 || declarations[0].Name() != "Consumer" {
		t.Fatalf("expected only the project class to be judged, found %v", declarations)
	}
	program := php.ProgramOf(codebase)
	if _, found := program.Declaration(`External\Client\Schema`); !found ||
		!program.OverridesMethod(`App\Records\Consumer`, "asArray") {
		t.Fatal("the dependency should still supply the inherited method")
	}
}

func TestOptionalWireRecordsAndBackedEnums(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
enum Phase: string { case Ready = 'ready'; }
enum Category { case Active; }
#[TypeScript]
final class Setting extends Data {
    public function __construct(public readonly string|Optional $requested = new Optional(),
        public readonly string|Optional $observed = new Optional()) {}
}
#[TypeScript]
final class Status extends Data {
    private ?Category $cache = null;
    public static ?Category $shared = null;
    public ?Category $visible = null;
    public function __construct(public readonly ?Phase $phase, public readonly ?Category $category,
        ?Category $input, private ?Category $internal, #[\Spatie\LaravelData\Attributes\Hidden] public ?Category $hidden) {}
}
final class Request extends Data {
    public function __construct(public readonly string|Optional $name = new Optional()) {}
}
`)
	bags := codebase.WhereKind("Stmt_Class").Where(engine.As(spatie.Node.EveryConstructorParamOptional)).Get()
	if len(bags) != 1 || bags[0].Name() != "Request" {
		for _, bag := range bags {
			t.Logf("%s is reported with attributes %v", bag.Name(), php.AttributeNames(bag))
			for _, param := range bag.Descendants() {
				if param.Kind() == "Param" {
					t.Logf("default kind %q and class %q (expected %q)", param.Child("default").Kind(), (php.Node{Match: param.Child("default")}).NewClassName(), spatie.Optional)
				}
			}
		}
		t.Fatal("only the non-wire input should be reported")
	}
	for kind, fieldName := range map[string]string{"Param": "category", "Stmt_Property": "visible"} {
		wire := codebase.WhereKind(kind).Where(engine.As(spatie.Node.NullableWireObject)).Get()
		if len(wire) != 1 {
			t.Errorf("expected one nullable wire field of kind %s, found %v", kind, wire)
			continue
		}
		field, _ := php.AsField(wire[0])
		if field.Name != fieldName {
			t.Errorf("a field outside the wire shape was reported: %s", field.Name)
		}
	}
}

func TestConstructorSideEffectsFollowExecution(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App;
class Direct {
    public function __construct(Service $service) { $service->warm(); }
}
class Deferred {
    public function __construct(Service $service) {
        $this->middleware(function () use ($service) { $service->warm(); });
    }
}
class Immediate {
    public function __construct(Service $service) {
        (function () use ($service) { $service->warm(); })();
    }
}
`)
	effects := codebase.WhereKind("Stmt_Class").Where(engine.As(php.Node.ConstructorHasSideEffect)).Get()
	if len(effects) != 2 || effects[0].Name() != "Direct" || effects[1].Name() != "Immediate" {
		t.Fatalf("expected the direct and immediately invoked effects, found %v", effects)
	}
}

func TestMatchDefaultsDistinguishEnumAnswersFromMissingValues(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App;
enum Status {
    case Open;
    case Closed;
    public function isOpen(): bool {
        return match ($this) { self::Open => true, default => false };
    }
    public function labels(): array {
        return match ($this) { self::Open => ['open'], default => [] };
    }
    public function label(): ?string {
        return match ($this) { self::Open => 'open', default => null };
    }
}
function enabled(string $feature): bool {
    return match ($feature) { 'ready' => true, default => false };
}
function rates(string $country): array {
    return match ($country) { 'NL' => [10], default => [] };
}
`)
	reported := codebase.WhereKind("Expr_Match").Where(engine.As(php.Node.IsMatchWithAbsenceDefault)).Get()
	if len(reported) != 3 {
		t.Fatalf("expected the nullable enum answer and two open-input defaults, found %v", reported)
	}
	for _, match := range reported {
		method := (php.Node{Match: match}).EnclosingFunctionLike().Name()
		if method != "label" && method != "enabled" && method != "rates" {
			t.Errorf("an ordinary answer on an enum was reported: %s", method)
		}
	}
}

func TestBuilderRefusalNamesAnExistingReplacement(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App;
use Illuminate\Database\Eloquent\Builder;
class Bounded extends Builder {
    public function chunk($size, $each) { throw ReadRefusal::method('chunk', 'readInChunks($size, $each)'); }
    public function readInChunks($size, $each) {}
    public function lazy($size) { throw ReadRefusal::method('lazy', 'missingReplacement'); }
}
`)
	closed := codebase.WhereKind("Stmt_ClassMethod").Where(engine.As(laravel.Node.ReplacesUnboundedBuilderRead)).Get()
	if len(closed) != 1 || closed[0].Name() != "chunk" {
		t.Fatalf("only the override with an existing replacement should be excused, found %v", closed)
	}
}

func TestControllerDelegationsDistinguishRequestOperations(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App;
class Open { public function run(Service $service, Request $request) { return $service->run($request->open()); } }
class Close { public function run(Service $service, Request $request) { return $service->run($request->close()); } }
class Repeat { public function run(Service $service, Request $request) { return $service->run($request->open()); } }
`)
	found := codebase.WhereKind("Stmt_ClassMethod").Get()
	if len(found) != 3 {
		t.Fatalf("expected three delegations, found %v", found)
	}
	open := (laravel.Node{Match: found[0]}).DelegatedOperation()
	close := (laravel.Node{Match: found[1]}).DelegatedOperation()
	repeat := (laravel.Node{Match: found[2]}).DelegatedOperation()
	if open == "" || open == close || open != repeat {
		t.Fatalf("expected open and close to differ and the repeated open to match, got %q, %q, %q", open, close, repeat)
	}
}

func TestScopeResolutionsRespectContainerBindingsAndTheirOwners(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App\Shop;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
interface Settings {}
final class ScopedSettings implements Settings {}
interface Policy {}
final class LongLived implements Policy {
    public function __construct(private Container $container) {}
    public function resolve(): object { return $this->container->make(ScopedSettings::class)->read(); }
}
final class Resolver {
    public function resolve(): Settings { return Container::getInstance()->make(Settings::class); }
}
final class Binder {
    public function boot(Container $container, Dispatcher $events): void {
        $container->singleton(Policy::class, LongLived::class);
        $container->scoped(ScopedSettings::class);
        $container->alias(ScopedSettings::class, Settings::class);
        $events->listen(Event::class, function () use ($container) {
            return Container::getInstance()->make(EventService::class);
        });
    }
    public function read(Container $container): object {
        return $container->make(Ordinary::class);
    }
}
`)
	found := codebase.WhereKind("Expr_MethodCall").
		Where(engine.As(laravel.Node.ResolvesFromContainer)).
		Where(engine.As(laravel.Node.ResolvesLateInALongerLivedOwner)).
		Get()
	if len(found) != 3 {
		for _, one := range found {
			t.Log(one.Written())
		}
		t.Fatal("the singleton's resolution, scoped alias and event callback should be excused")
	}
}

func TestValidationMessagesAreNoResolvedObject(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App\Shop;
final class Grammar {
    public function problem(string $key): string|null { return null; }
    public function lookup(string $key): Entry { return new Entry(); }
}
final class Registry {
    public function validate(Grammar $grammar, string $key): void {
        $problem = $grammar->problem($key);
        if ($problem !== null) { throw Report::invalid($problem); }
    }
    public function apply(Grammar $grammar, string $key): void {
        $entry = $grammar->lookup($key);
        $entry->apply();
    }
}
`)
	found := codebase.WhereKind("Stmt_ClassMethod").Where(engine.As(php.Node.UnpacksTargetFromContainerParam)).Get()
	if len(found) != 1 || found[0].Name() != "apply" {
		t.Fatalf("only the object lookup should be reported, found %v", found)
	}
}

func TestCallbackGuardsKeepTheirScopes(t *testing.T) {
	codebase := phpSource(t, `<?php
final class Lifecycle {
    private array $running = [];
    private string $scratch = '';
    public function settle(string $id): void {
        $before = $this->running;
        $this->running[$id] = true;
        try { $this->runCallbacks($id); }
        finally { $this->running = $before; }
    }
    public function isRunning(string $id): bool { return isset($this->running[$id]); }
    public function calculate(string $id): void {
        $before = $this->scratch;
        $this->scratch = $id;
        try { $this->work(); }
        finally { $this->scratch = $before; }
    }
}
`)
	found := codebase.WhereKind("Stmt_ClassMethod").Where(engine.As(php.Node.HasOwnStateSaveAndRestore)).Get()
	if len(found) != 1 || found[0].Name() != "calculate" {
		t.Fatalf("only the scratch field should be reported, found %v", found)
	}
}

func TestDocParagraphsExcludeMultilineTypeTags(t *testing.T) {
	const text = `/**
 * A checkpoint holds its read position.
 *
 * @phpstan-type Fields array{
 *   offset: int,
 *
 *   tags: list<string>,
 * }
 */`
	if paragraphs := php.DocParagraphs(text); paragraphs != 1 {
		t.Fatalf("type declarations do not add prose paragraphs, found %d", paragraphs)
	}
}

func TestDocParagraphsExcludeFencedExamples(t *testing.T) {
	const example = `/**
 * A checkout card shows the current delivery address.
 *
 * ~~~blade
 * @include('delivery.address')
 *
 * @include('delivery.phone')
 * ~~~`
	if paragraphs := php.DocParagraphs(example + "\n */"); paragraphs != 1 {
		t.Fatalf("a fenced example should not add a paragraph, found %d", paragraphs)
	}
	if paragraphs := php.DocParagraphs(example + "\n * The checkout validates the address before shipping.\n */"); paragraphs != 2 {
		t.Fatalf("prose after an example should still count, found %d", paragraphs)
	}
}

func TestOpenMapsAndExternalPayloadsKeepTheirWireShapes(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App\Shop;
final class Payload {
    /** @param array<string, mixed> $args */
    public function __construct(public array $args) {}
}
final class Parser {
    public function relay(string $json): string {
        $body = json_decode($json, true);
        $this->inspect($body);
        return json_encode($body);
    }
    private function inspect(array $body): void { echo $body['version']; }
    public static function read(array $json): Result {
        if (! is_array($json['rows'])) { throw Invalid::rows(); }
        return Result::fromArray($json['rows']);
    }
    public static function environment(): array {
        return ['PATH' => '/bin', 'HOME' => '/tmp', 'APP_ENV' => 'test'];
    }
}
final class Result {
    public static function fromArray(array $rows): self { return new self(); }
}
`)
	if maps := codebase.WhereKind("Param").Where(engine.As(php.Node.CarriesOpenMap)).Get(); len(maps) != 1 {
		t.Fatalf("expected the explicitly open map, found %v", maps)
	}
	if forwarded := codebase.WhereKind("Expr_ArrayDimFetch").Where(engine.As(php.Node.ReadsRelayedJSON)).Get(); len(forwarded) != 1 {
		t.Fatalf("expected one read of forwarded JSON, found %v", forwarded)
	}
	if fields := codebase.WhereKind("Expr_ArrayDimFetch").Where(engine.As(php.Node.ParsesArrayField)).Get(); len(fields) != 2 {
		t.Fatalf("expected two reads that validate and construct the result, found %v", fields)
	}
	if env := codebase.WhereKind("Expr_Array").Where(engine.As(php.Node.ReturnsProcessEnvironment)).Get(); len(env) != 1 {
		t.Fatalf("expected only the environment map, found %v", env)
	}
}
