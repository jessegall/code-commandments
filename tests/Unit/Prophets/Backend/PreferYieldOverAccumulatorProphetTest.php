<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferYieldOverAccumulatorProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use PHPUnit\Framework\TestCase;

class PreferYieldOverAccumulatorProphetTest extends TestCase
{
    private PreferYieldOverAccumulatorProphet $prophet;

    protected function setUp(): void
    {
        $this->prophet = new PreferYieldOverAccumulatorProphet();
    }

    public function test_fires_on_the_issue_before_example_threading_an_accumulator(): void
    {
        // The #126 GraphValidator "before" shape: a void check* method whose only
        // job is to write into a passed-in GraphValidationAccumulator, threaded
        // through several check* methods.
        $judgment = $this->judge(<<<'PHP'
final class GraphValidationAccumulator
{
    private array $errors = [];

    public function missingInput(string $nodeId, string $name): void { $this->errors[] = [$nodeId, $name]; }
    public function error(string $message): void { $this->errors[] = $message; }
    public function markInvalid(string $nodeId): void { $this->errors[] = $nodeId; }
    public function finalize(): array { return $this->errors; }
}

final class GraphValidator
{
    private function checkRequiredInputs(WorkflowGraph $g, WorkflowNode $node, GraphValidationAccumulator $acc): void
    {
        foreach ($node->inputs as $input) {
            if ($this->missing($g, $node, $input)) {
                $acc->missingInput($node->id, $input->name);
                $acc->error("Required input '{$input->name}' is unwired");
                $acc->markInvalid($node->id);
            }
        }
    }

    private function checkOutputs(WorkflowGraph $g, WorkflowNode $node, GraphValidationAccumulator $acc): void
    {
        foreach ($node->outputs as $output) {
            $acc->error("Output {$output->name} dangling");
        }
    }

    private function checkCycles(WorkflowGraph $g, WorkflowNode $node, GraphValidationAccumulator $acc): void
    {
        if ($this->hasCycle($g, $node)) {
            $acc->markInvalid($node->id);
            $acc->error('cycle');
        }
    }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('output parameter threaded through 3 methods', $judgment->warnings[0]->message);
        $this->assertStringContainsString('$acc', $judgment->warnings[0]->message);
    }

    public function test_fires_on_god_accumulator_threaded_through_many_methods(): void
    {
        $judgment = $this->judge(<<<'PHP'
final class Diagnostics
{
    private array $items = [];
    public function add(string $m): void { $this->items[] = $m; }
    public function warn(string $m): void { $this->items[] = $m; }
    public function fail(string $m): void { $this->items[] = $m; }
    public function toArray(): array { return $this->items; }
}

final class GraphValidator
{
    private function checkA(Node $n, Diagnostics $d): void { $d->add('a'); }
    private function checkB(Node $n, Diagnostics $d): void { $d->warn('b'); }
    private function checkC(Node $n, Diagnostics $d): void { $d->fail('c'); }
    private function checkD(Node $n, Diagnostics $d): void { $d->add('d'); }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('threaded through 4 methods', $judgment->warnings[0]->message);
    }

    public function test_does_not_fire_on_a_fluent_builder(): void
    {
        // Mutators return self/$this -> NOT predominantly void.
        $judgment = $this->judge(<<<'PHP'
final class QueryBuilder
{
    private array $parts = [];
    public function where(string $c): self { $this->parts[] = $c; return $this; }
    public function orderBy(string $c): self { $this->parts[] = $c; return $this; }
    public function limit(int $n): self { $this->parts[] = $n; return $this; }
    public function toArray(): array { return $this->parts; }
}

final class QueryAssembler
{
    private function applyFilters(Spec $s, QueryBuilder $q): void { $q->where('a'); }
    private function applySort(Spec $s, QueryBuilder $q): void { $q->orderBy('b'); }
    private function applyLimit(Spec $s, QueryBuilder $q): void { $q->limit(10); }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_does_not_fire_on_a_builder_registration_hook(): void
    {
        // build(Pipeline $p): void — the Pipeline is a builder you configure, not a
        // result collector with a terminal accessor over gathered findings.
        $judgment = $this->judge(<<<'PHP'
final class Pipeline
{
    private array $stages = [];
    public function pipe(callable $stage): void { $this->stages[] = $stage; }
    public function through(string $stage): void { $this->stages[] = $stage; }
    public function run(mixed $input): mixed { return $input; }
}

final class PipelineDefinition
{
    public function build(Pipeline $p): void { $p->pipe(fn ($x) => $x); }
    public function buildAuth(Pipeline $p): void { $p->through('auth'); }
    public function buildLog(Pipeline $p): void { $p->through('log'); }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_does_not_fire_on_an_event_sourcing_aggregate(): void
    {
        // recordThat() into an aggregate is by design — the aggregate IS the sink.
        $judgment = $this->judge(<<<'PHP'
final class Cart
{
    private array $events = [];
    public function recordThat(object $event): void { $this->events[] = $event; }
    public function apply(object $event): void { $this->events[] = $event; }
    public function releaseEvents(): array { return $this->events; }
}

final class CartService
{
    private function handleAdd(Command $c, Cart $cart): void { $cart->recordThat(new ItemAdded()); }
    private function handleRemove(Command $c, Cart $cart): void { $cart->recordThat(new ItemRemoved()); }
    private function handleClear(Command $c, Cart $cart): void { $cart->recordThat(new Cleared()); }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_does_not_fire_on_a_visitor(): void
    {
        $judgment = $this->judge(<<<'PHP'
final class Acc
{
    private array $out = [];
    public function push(string $x): void { $this->out[] = $x; }
    public function result(): array { return $this->out; }
}

final class PrintingVisitor extends NodeVisitor
{
    private function visitLeaf(Leaf $n, Acc $acc): void { $acc->push('leaf'); }
    private function visitBranch(Branch $n, Acc $acc): void { $acc->push('branch'); }
    private function visitRoot(Root $n, Acc $acc): void { $acc->push('root'); }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_does_not_fire_on_a_single_write_only_helper_below_min_methods(): void
    {
        // Only ONE method threads the collector -> below min_methods (3).
        $judgment = $this->judge(<<<'PHP'
final class Acc
{
    private array $out = [];
    public function add(string $x): void { $this->out[] = $x; }
    public function build(): array { return $this->out; }
}

final class Reporter
{
    private function reportOnce(Thing $t, Acc $acc): void { $acc->add('x'); }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_does_not_fire_when_the_param_is_read(): void
    {
        // The param is READ (passed on / dereferenced) in some methods -> not write-only.
        $judgment = $this->judge(<<<'PHP'
final class Acc
{
    private array $out = [];
    public function add(string $x): void { $this->out[] = $x; }
    public function count(): int { return count($this->out); }
    public function result(): array { return $this->out; }
}

final class Reader
{
    private function a(Thing $t, Acc $acc): void { $acc->add('a'); }
    private function b(Thing $t, Acc $acc): void { $this->store($acc); }
    private function c(Thing $t, Acc $acc): void { $n = $acc->count(); echo $n; }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_does_not_fire_on_the_after_yield_shape(): void
    {
        // The refactored shape: pure functions that yield typed results, no
        // accumulator parameter at all.
        $judgment = $this->judge(<<<'PHP'
final class GraphValidator
{
    protected function checkNode(GraphValidationContext $ctx, WorkflowNode $node): iterable
    {
        foreach ($node->inputs as $input) {
            if ($this->missing($ctx, $node, $input)) {
                yield new MissingRequiredInput($node->id, $input->name);
            }
        }
    }

    protected function checkOutputs(GraphValidationContext $ctx, WorkflowNode $node): iterable
    {
        foreach ($node->outputs as $output) {
            yield new DanglingOutput($output->name);
        }
    }

    protected function checkCycles(GraphValidationContext $ctx, WorkflowNode $node): iterable
    {
        if ($this->hasCycle($ctx, $node)) {
            yield new CycleDetected($node->id);
        }
    }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_min_methods_is_configurable(): void
    {
        $source = <<<'PHP'
final class Acc
{
    private array $out = [];
    public function add(string $x): void { $this->out[] = $x; }
    public function build(): array { return $this->out; }
}

final class Reporter
{
    private function a(Thing $t, Acc $acc): void { $acc->add('a'); }
    private function b(Thing $t, Acc $acc): void { $acc->add('b'); }
}
PHP;

        // Two threading methods: silent at the default min_methods (3) ...
        $this->assertCount(0, $this->judge($source)->warnings);

        // ... but fires when the threshold is lowered to 2.
        $prophet = new PreferYieldOverAccumulatorProphet();
        $prophet->configure(['min_methods' => 2]);
        $judgment = $prophet->judge('/x.php', "<?php\n\nnamespace App;\n\n{$source}\n");

        $this->assertCount(1, $judgment->warnings);
    }

    // --- Regression: LEAVE-WHEN false positives the red-team found (#126 exemptions) ---

    public function test_does_not_fire_on_a_builder_whose_product_is_a_collection(): void
    {
        // THE KILLER: build(): ItemCollection is a builder PRODUCT, not a gather-
        // accessor — even though the product type ends in "Collection". A non-fluent
        // builder threaded through director methods must stay quiet (#126 LEAVE:
        // "a PipelineDefinition::build ... a builder you do not own").
        $judgment = $this->judge(<<<'PHP'
final class ItemCollection {}

final class CartBuilder
{
    private array $items = [];
    public function addItem(string $sku): void { $this->items[] = $sku; }
    public function addCoupon(string $code): void { $this->items[] = $code; }
    public function addGift(string $gift): void { $this->items[] = $gift; }
    public function build(): ItemCollection { return new ItemCollection(); }
}

final class CheckoutDirector
{
    private function withItems(Cart $c, CartBuilder $b): void { $b->addItem('a'); }
    private function withCoupons(Cart $c, CartBuilder $b): void { $b->addCoupon('x'); }
    private function withGifts(Cart $c, CartBuilder $b): void { $b->addGift('g'); }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_does_not_fire_on_a_container_registration_hook(): void
    {
        // A Container you CONFIGURE via register/bind/singleton, with an incidental
        // getBindings(): array getter — the exact registration-hook crux #126 names.
        $judgment = $this->judge(<<<'PHP'
final class Container
{
    private array $bindings = [];
    public function register(string $id, callable $f): void { $this->bindings[$id] = $f; }
    public function bind(string $id, callable $f): void { $this->bindings[$id] = $f; }
    public function singleton(string $id, callable $f): void { $this->bindings[$id] = $f; }
    public function getBindings(): array { return $this->bindings; }
}

final class AppServiceProvider
{
    private function registerCore(App $a, Container $c): void { $c->register('a', fn () => 1); }
    private function registerAuth(App $a, Container $c): void { $c->bind('b', fn () => 2); }
    private function registerCache(App $a, Container $c): void { $c->singleton('c', fn () => 3); }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_does_not_fire_on_a_route_collection_registrar(): void
    {
        // RouteCollection { void add/remove; all(): array } configured by registrar
        // methods — a framework-owned registry you add into, not a result collector.
        $judgment = $this->judge(<<<'PHP'
final class RouteCollection
{
    private array $routes = [];
    public function add(string $method, string $uri): void { $this->routes[$method] = $uri; }
    public function remove(string $uri): void { unset($this->routes[$uri]); }
    public function all(): array { return $this->routes; }
}

final class RouteRegistrar
{
    private function registerWeb(Router $r, RouteCollection $c): void { $c->add('GET', '/'); }
    private function registerApi(Router $r, RouteCollection $c): void { $c->add('POST', '/api'); }
    private function registerAdmin(Router $r, RouteCollection $c): void { $c->add('GET', '/admin'); }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_does_not_fire_on_an_intent_named_aggregate_with_event_puller(): void
    {
        // An aggregate whose mutators are intent-named (addLine/applyDiscount/cancel)
        // and whose puller is pullEvents(): array — no magic recordThat/apply names.
        // Still a sink-by-design (#126 LEAVE: event-sourcing aggregate).
        $judgment = $this->judge(<<<'PHP'
final class Order
{
    private array $domainEvents = [];
    public function addLine(string $sku, int $qty): void { $this->domainEvents[] = $sku; }
    public function applyDiscount(string $code): void { $this->domainEvents[] = $code; }
    public function cancel(string $reason): void { $this->domainEvents[] = $reason; }
    public function pullEvents(): array { $e = $this->domainEvents; $this->domainEvents = []; return $e; }
}

final class OrderHandler
{
    private function handlePlace(Command $c, Order $o): void { $o->addLine('a', 1); }
    private function handleDiscount(Command $c, Order $o): void { $o->applyDiscount('x'); }
    private function handleCancel(Command $c, Order $o): void { $o->cancel('y'); }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_does_not_fire_on_a_visitor_implementing_an_interface(): void
    {
        // The common visitor shape is `implements NodeVisitor`, not `extends` —
        // nodeIsExcludedBase must inspect implements too.
        $judgment = $this->judge(<<<'PHP'
final class Acc
{
    private array $out = [];
    public function push(string $x): void { $this->out[] = $x; }
    public function result(): array { return $this->out; }
}

final class TreeWalker implements NodeVisitor
{
    private function visitLeaf(Leaf $n, Acc $acc): void { $acc->push('leaf'); }
    private function visitBranch(Branch $n, Acc $acc): void { $acc->push('branch'); }
    private function visitRoot(Root $n, Acc $acc): void { $acc->push('root'); }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_does_not_fire_on_a_mostly_fluent_builder_with_void_escape_hatches(): void
    {
        // FormBuilder: 2 fluent setters + 2 void escape-hatch helpers + toArray():array.
        // A predominantly-fluent builder with a couple of void helpers must not tip
        // into "collector" — the strict `fluent > void` comparison was too coarse.
        $judgment = $this->judge(<<<'PHP'
final class FormBuilder
{
    private array $fields = [];
    public function add(string $f): self { $this->fields[] = $f; return $this; }
    public function with(string $f): self { $this->fields[] = $f; return $this; }
    public function reset(): void { $this->fields = []; }
    public function clear(): void { $this->fields = []; }
    public function toArray(): array { return $this->fields; }
}

final class FormDirector
{
    private function applyBase(Spec $s, FormBuilder $b): void { $b->reset(); }
    private function applyExtra(Spec $s, FormBuilder $b): void { $b->clear(); }
    private function applyFinal(Spec $s, FormBuilder $b): void { $b->reset(); }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_does_not_fire_on_an_unresolvable_bag_config_param_via_name_alone(): void
    {
        // An unresolvable \Vendor\Pkg\OptionsBag (not in this file, not loadable)
        // threaded through register-style void setters must NOT fire on the "Bag"
        // suffix alone — with no shape to inspect, the name booster only applies when
        // the threaded calls look like appends, never registrations.
        $judgment = $this->judge(<<<'PHP'
final class Configurator
{
    private function setupA(App $a, \Vendor\Pkg\OptionsBag $o): void { $o->set('a', 1); }
    private function setupB(App $a, \Vendor\Pkg\OptionsBag $o): void { $o->set('b', 2); }
    private function setupC(App $a, \Vendor\Pkg\OptionsBag $o): void { $o->set('c', 3); }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_does_not_fire_on_a_builder_whose_product_is_a_concrete_object(): void
    {
        // S1: build(): HttpRequest — a concrete-object product. The control case that
        // must STAY quiet (and did before the tightening); guards the build()/make()
        // product-discriminator from over-firing.
        $judgment = $this->judge(<<<'PHP'
final class HttpRequest {}

final class RequestBuilder
{
    private array $headers = [];
    public function withHeader(string $k, string $v): void { $this->headers[$k] = $v; }
    public function withQuery(string $k, string $v): void { $this->headers[$k] = $v; }
    public function withBody(string $b): void { $this->headers[] = $b; }
    public function build(): HttpRequest { return new HttpRequest(); }
}

final class RequestDirector
{
    private function a(Spec $s, RequestBuilder $b): void { $b->withHeader('a', '1'); }
    private function b(Spec $s, RequestBuilder $b): void { $b->withQuery('a', '1'); }
    private function c(Spec $s, RequestBuilder $b): void { $b->withBody('z'); }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_does_not_fire_on_a_partly_fluent_builder_with_array_product(): void
    {
        // 1c: ReportBuilder — 2 self setters + 3 void adders + build(): array. The
        // build()/make() product overrides the array-getter "terminal" signal.
        $judgment = $this->judge(<<<'PHP'
final class ReportBuilder
{
    private array $f = [];
    public function setTitle(string $x): self { $this->f[] = $x; return $this; }
    public function setAuthor(string $x): self { $this->f[] = $x; return $this; }
    public function addRow(string $x): void { $this->f[] = $x; }
    public function addCol(string $x): void { $this->f[] = $x; }
    public function addNote(string $x): void { $this->f[] = $x; }
    public function build(): array { return $this->f; }
}

final class ReportDirector
{
    private function a(Spec $s, ReportBuilder $b): void { $b->addRow('a'); }
    private function b(Spec $s, ReportBuilder $b): void { $b->addCol('b'); }
    private function c(Spec $s, ReportBuilder $b): void { $b->addNote('c'); }
}
PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_fires_on_a_predominantly_void_collector_with_one_fluent_helper(): void
    {
        // A collector that is mostly void mutators with a SINGLE incidental fluent
        // helper (void 3 > fluent 1) is still predominantly void — it must still fire.
        // Guards the relaxed fluent comparison from over-suppressing genuine collectors.
        $judgment = $this->judge(<<<'PHP'
final class Diag
{
    private array $d = [];
    public function add(string $m): void { $this->d[] = $m; }
    public function note(string $m): void { $this->d[] = $m; }
    public function warn(string $m): void { $this->d[] = $m; }
    public function tap(string $m): self { $this->d[] = $m; return $this; }
    public function toArray(): array { return $this->d; }
}

final class Auditor
{
    private function a(Node $n, Diag $d): void { $d->add('a'); }
    private function b(Node $n, Diag $d): void { $d->note('b'); }
    private function c(Node $n, Diag $d): void { $d->warn('c'); }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('threaded through 3 methods', $judgment->warnings[0]->message);
    }

    // --- Regression: missed TRUE POSITIVE — property-array-append writes ---

    public function test_fires_when_methods_append_to_a_public_array_field_of_the_collector(): void
    {
        // The same god-accumulator smell expressed via a public array FIELD instead
        // of a mutator method: `$acc->errors[] = '…'`. Shape is a clear collector
        // (no mutator surface, just a public field + toArray()), so it's the suffix
        // that confirms it, and the property-append is recognised as a write.
        $judgment = $this->judge(<<<'PHP'
final class ValidationErrors
{
    public array $errors = [];
    public function toArray(): array { return $this->errors; }
}

final class GraphValidator
{
    private function checkA(Node $n, ValidationErrors $acc): void { $acc->errors[] = 'a'; }
    private function checkB(Node $n, ValidationErrors $acc): void { $acc->errors[] = 'b'; }
    private function checkC(Node $n, ValidationErrors $acc): void { $acc->errors[] = 'c'; }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('threaded through 3 methods', $judgment->warnings[0]->message);
    }

    public function test_fires_on_a_mixed_mutator_and_property_append_accumulator(): void
    {
        // Mixed: two methods use a mutator, one appends to the public field. All three
        // are write-only, so the count stays at min_methods and it fires.
        $judgment = $this->judge(<<<'PHP'
final class Diagnostics
{
    public array $items = [];
    public function add(string $m): void { $this->items[] = $m; }
    public function toArray(): array { return $this->items; }
}

final class Validator
{
    private function checkA(Node $n, Diagnostics $d): void { $d->add('a'); }
    private function checkB(Node $n, Diagnostics $d): void { $d->add('b'); }
    private function checkC(Node $n, Diagnostics $d): void { $d->items[] = 'c'; }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('threaded through 3 methods', $judgment->warnings[0]->message);
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n\nnamespace App;\n\n{$body}\n");
    }
}
