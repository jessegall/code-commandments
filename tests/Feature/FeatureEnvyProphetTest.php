<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Prophets\Backend\FeatureEnvyProphet;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tests\TestCase;

class FeatureEnvyProphetTest extends TestCase
{
    private ScrollManager $manager;

    private ProphetRegistry $registry;

    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ProphetRegistry;
        $this->manager = new ScrollManager($this->registry, new GenericFileScanner);

        $this->fixtureDir = realpath(__DIR__ . '/../Fixtures/Backend/Sinful/FeatureEnvy');
        $this->assertNotFalse($this->fixtureDir);

        $this->registry->registerMany('test', [FeatureEnvyProphet::class => []]);
        $this->registry->setScrollConfig('test', [
            'path' => $this->fixtureDir,
            'extensions' => ['php'],
        ]);
    }

    public function test_flags_a_query_over_a_foreign_owned_object(): void
    {
        $results = $this->manager->judgeScroll('test');
        $warnings = $this->warningsFor($results, $this->fixtureDir . '/EnvyResolver.php');

        $methods = array_map(static fn (Warning $w): string => $w->message, $warnings);

        $this->assertNotEmpty($warnings, 'EnvyResolver queries NodeDescriptor internals — flag it.');
        $this->assertTrue(
            (bool) array_filter($methods, static fn (string $m): bool => str_contains($m, 'findOutput')),
            'findOutput should be flagged.',
        );
        $this->assertStringContainsString('NodeDescriptor', $warnings[0]->message);
        $this->assertStringContainsString('tell-don\'t-ask', $warnings[0]->message);
    }

    public function test_flags_in_array_over_a_foreign_collection_method(): void
    {
        $results = $this->manager->judgeScroll('test');
        $messages = array_map(static fn (Warning $w): string => $w->message, $this->warningsFor($results, $this->fixtureDir . '/EnvyResolver.php'));

        $this->assertTrue(
            (bool) array_filter($messages, static fn (string $m): bool => str_contains($m, 'isControlHandle')),
            'isControlHandle (in_array over $descriptor->...HandleNames()) should be flagged.',
        );
    }

    public function test_does_not_flag_querying_your_own_collection(): void
    {
        // NodeDescriptor::findOutputHere queries $this->outputs — own data.
        $results = $this->manager->judgeScroll('test');
        $warnings = $this->warningsFor($results, $this->fixtureDir . '/NodeDescriptor.php');

        $this->assertEmpty($warnings, 'Querying $this own collection is not feature envy.');
    }

    public function test_does_not_flag_a_method_touching_no_foreign_object(): void
    {
        $results = $this->manager->judgeScroll('test');
        $messages = array_map(static fn (Warning $w): string => $w->message, $this->warningsFor($results, $this->fixtureDir . '/EnvyResolver.php'));

        foreach ($messages as $message) {
            $this->assertStringNotContainsString('ownWork', $message);
        }
    }

    public function test_does_not_flag_a_data_mapper_array_map(): void
    {
        // array_map(fn => *Data::from(...), $foreign->coll) is a presentation
        // mapper — moving it onto the domain owner would invert the dependency.
        $results = $this->manager->judgeScroll('test');
        $messages = array_map(static fn (Warning $w): string => $w->message, $this->warningsFor($results, $this->fixtureDir . '/EnvyResolver.php'));

        foreach ($messages as $message) {
            $this->assertStringNotContainsString('toDtos', $message);
        }
    }

    public function test_does_not_flag_a_query_over_a_serialization_boundary(): void
    {
        // array_map(…, $foreign->toArray()) reads the exported form, not internals.
        $results = $this->manager->judgeScroll('test');
        $messages = array_map(static fn (Warning $w): string => $w->message, $this->warningsFor($results, $this->fixtureDir . '/EnvyResolver.php'));

        foreach ($messages as $message) {
            $this->assertStringNotContainsString('summarise', $message);
        }
    }

    public function test_stays_silent_without_an_index(): void
    {
        $prophet = new FeatureEnvyProphet;
        $content = file_get_contents($this->fixtureDir . '/EnvyResolver.php');

        $this->assertTrue($prophet->judge($this->fixtureDir . '/EnvyResolver.php', $content)->isRighteous());
    }

    /**
     * @param  Collection<string, mixed>  $results
     * @return list<Warning>
     */
    private function warningsFor(Collection $results, string $file): array
    {
        if (! $results->has($file)) {
            return [];
        }

        $judgment = $results->get($file)->get(FeatureEnvyProphet::class);

        return $judgment === null ? [] : array_values($judgment->warnings);
    }

    // ── #61: false-positive refinements ──────────────────────────────────

    public function test_flags_a_foreach_search_over_a_foreign_collection(): void
    {
        // #95: `foreach ($descriptor->outputs as $o) { if (…) return $o; }` is a
        // SEARCH over another object's collection — a query that belongs on it
        // (NodeDescriptor::findOutput). A plain foreach doing own work is not.
        $dir = sys_get_temp_dir() . '/cc-fe95-' . uniqid();
        @mkdir($dir, 0755, true);
        $dir = realpath($dir);
        $ns = 'JesseGall\\CodeCommandments\\Tests\\Fixtures\\Backend\\Sinful\\FeatureEnvy';

        file_put_contents("$dir/NodeDescriptor.php", "<?php\nnamespace {$ns};\nclass NodeDescriptor { public array \$outputs = []; }\n");
        file_put_contents("$dir/Registry.php", "<?php\nnamespace {$ns};\nclass Registry {\n public function findOutputSocket(NodeDescriptor \$descriptor, string \$portName) {\n  foreach (\$descriptor->outputs as \$output) {\n   if (\$output->name === \$portName) { return \$output; }\n  }\n  return null;\n }\n}\n");
        file_put_contents("$dir/Worker.php", "<?php\nnamespace {$ns};\nclass Worker {\n public function run(NodeDescriptor \$descriptor) { foreach (\$descriptor->outputs as \$o) { \$this->p(\$o); } }\n public function p(\$x) {}\n}\n");

        $registry = new ProphetRegistry;
        $manager = new ScrollManager($registry, new GenericFileScanner);
        $registry->registerMany('t', [FeatureEnvyProphet::class => []]);
        $registry->setScrollConfig('t', ['path' => $dir, 'extensions' => ['php']]);

        $results = $manager->judgeScroll('t');

        $this->assertNotEmpty($this->warningsFor($results, "$dir/Registry.php"), 'a foreach search over a foreign collection is feature envy.');
        $this->assertEmpty($this->warningsFor($results, "$dir/Worker.php"), 'a plain foreach doing own work is not.');

        foreach (glob("$dir/*.php") ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    public function test_does_not_warn_on_a_call_to_an_unindexed_method(): void
    {
        // Regression: resolving a call's @return generic must not emit an
        // "Undefined array key" warning when the method isn't in the class's
        // method map (a `?->` after an array access does not suppress it).
        $dir = sys_get_temp_dir() . '/cc-fe-' . uniqid();
        @mkdir($dir, 0755, true);
        $ns = 'JesseGall\\CodeCommandments\\Tests\\Fixtures\\Backend\\Sinful\\FeatureEnvy';

        file_put_contents("$dir/Bag.php", "<?php\nnamespace {$ns};\nclass Bag { public array \$items = []; }\n");
        file_put_contents("$dir/Svc.php", "<?php\nnamespace {$ns};\nclass Svc { public function m(Bag \$bag) { \$x = \$bag->get('k'); \$y = \$bag->toArray(); return [\$x, \$y]; } }\n");

        $index = CodebaseIndex::build(glob("$dir/*.php") ?: []);
        $prophet = new FeatureEnvyProphet;
        $prophet->setCodebaseIndex($index);

        $errors = [];
        set_error_handler(static function (int $no, string $str) use (&$errors): bool {
            $errors[] = $str;

            return true;
        });

        try {
            $prophet->judge("$dir/Svc.php", file_get_contents("$dir/Svc.php"));
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $errors, 'No PHP warnings should be emitted.');

        foreach (glob("$dir/*.php") ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    public function test_flags_a_query_over_an_unwrapped_option_chain(): void
    {
        // #75: the collection owner is reached by unwrapping an Option<T> —
        // `$opt->getOrThrow()->controlHandleNames()`. Resolve T (NodeDescriptor)
        // from the `@return Option<NodeDescriptor>`-typed call $opt came from.
        $dir = sys_get_temp_dir() . '/cc-envy75-' . uniqid();
        @mkdir($dir, 0755, true);
        $dir = realpath($dir);
        $ns = 'JesseGall\\CodeCommandments\\Tests\\Fixtures\\Backend\\Sinful\\FeatureEnvy';

        file_put_contents("$dir/NodeDescriptor.php", "<?php\nnamespace {$ns};\nclass NodeDescriptor { public array \$names = []; public function controlHandleNames(): array { return \$this->names; } }\n");
        file_put_contents("$dir/Graph.php", "<?php\nnamespace {$ns};\nclass Option { public function getOrThrow() { return null; } }\nclass WorkflowGraph {\n /** @return Option<NodeDescriptor> */\n public function nodeById(int \$id): Option { return new Option(); }\n}\n");
        file_put_contents("$dir/Edges.php", "<?php\nnamespace {$ns};\nclass Edges {\n public function isControlEdge(WorkflowGraph \$graph, int \$id, string \$port): bool {\n  \$option = \$graph->nodeById(\$id);\n  return in_array(\$port, \$option->getOrThrow()->controlHandleNames(), true);\n }\n}\n");

        $registry = new ProphetRegistry;
        $manager = new ScrollManager($registry, new GenericFileScanner);
        $registry->registerMany('t', [FeatureEnvyProphet::class => []]);
        $registry->setScrollConfig('t', ['path' => $dir, 'extensions' => ['php']]);

        $results = $manager->judgeScroll('t');
        $warnings = $this->warningsFor($results, "$dir/Edges.php");

        $this->assertNotEmpty($warnings, 'query over an unwrapped Option chain is still feature envy on the inner type.');
        $this->assertStringContainsString('NodeDescriptor', $warnings[0]->message);

        foreach (glob("$dir/*.php") ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    public function test_does_not_flag_api_calls_injected_deps_or_form_requests(): void
    {
        $dir = sys_get_temp_dir() . '/cc-envy-' . uniqid();
        @mkdir($dir, 0755, true);
        $dir = realpath($dir);   // judgeScroll keys results by realpath
        $ns = 'JesseGall\\CodeCommandments\\Tests\\Fixtures\\Backend\\Sinful\\FeatureEnvy';

        // Owner types — project-owned (in the index).
        file_put_contents("$dir/WorkflowGraph.php", "<?php\nnamespace {$ns};\nclass WorkflowGraph {}\n");
        file_put_contents("$dir/SchemaInferrer.php", "<?php\nnamespace {$ns};\nclass SchemaInferrer {}\n");
        file_put_contents("$dir/StoreRequest.php", "<?php\nnamespace {$ns};\nclass StoreRequest {}\n");
        file_put_contents("$dir/Descriptor.php", "<?php\nnamespace {$ns};\nclass Descriptor { public array \$outputs = []; }\n");

        // (A) query over a foreign method WITH ARGS — the object's query API.
        file_put_contents("$dir/Emitter.php", "<?php\nnamespace {$ns};\nclass Emitter { public function wire(WorkflowGraph \$graph, \$id, \$port) { return Option::first(\$graph->edgesIntoSocket(\$id, \$port), fn (\$e) => \$e); } }\n");
        // (B) method on an injected dependency — delegation.
        file_put_contents("$dir/Ctrl.php", "<?php\nnamespace {$ns};\nclass Ctrl { public function __construct(private SchemaInferrer \$inferrer) {} public function infer() { return Option::first(\$this->inferrer->infer(), fn (\$x) => \$x); } }\n");
        // (C) FormRequest typed getter — what the toolkit itself requires.
        file_put_contents("$dir/Ctrl2.php", "<?php\nnamespace {$ns};\nclass Ctrl2 { public function store(StoreRequest \$request) { return Option::first(\$request->fieldSpecs(), fn (\$s) => \$s); } }\n");
        // CONTROL — a raw property read on a foreign param is still envy.
        file_put_contents("$dir/Wirer.php", "<?php\nnamespace {$ns};\nclass Wirer { public function wire(Descriptor \$descriptor) { return Option::first(\$descriptor->outputs, fn (\$o) => \$o); } }\n");

        $registry = new ProphetRegistry;
        $manager = new ScrollManager($registry, new GenericFileScanner);
        $registry->registerMany('t', [FeatureEnvyProphet::class => []]);
        $registry->setScrollConfig('t', ['path' => $dir, 'extensions' => ['php']]);

        $results = $manager->judgeScroll('t');

        $this->assertEmpty($this->warningsFor($results, "$dir/Emitter.php"), '(A) a parameterised query method is the API, not envy.');
        $this->assertEmpty($this->warningsFor($results, "$dir/Ctrl.php"), '(B) delegating to an injected dependency is not envy.');
        $this->assertEmpty($this->warningsFor($results, "$dir/Ctrl2.php"), '(C) a FormRequest typed getter is required, not envy.');
        $this->assertNotEmpty($this->warningsFor($results, "$dir/Wirer.php"), 'CONTROL: a raw property read on a foreign param IS still envy.');

        foreach (glob("$dir/*.php") ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
