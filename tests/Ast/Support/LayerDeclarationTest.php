<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\NamespaceGraph;
use JesseGall\CodeCommandments\Detectors\Backend\NamespaceDependencyDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The contract `commandments layers` rests on: what it emits must PASS. A proposed declaration that
 * fails on the next judge is worse than none — it hands you a config that is already broken and
 * teaches you to distrust the rule.
 *
 * So the centrepiece here is one invariant run over every shape a real tree throws at it: emit the
 * declaration, feed it to the detector that reads it, and demand silence. The named tests around it
 * pin the individual decisions that make that invariant hold.
 */
final class LayerDeclarationTest extends TestCase
{
    // ── the invariant ────────────────────────────────────────────────────────────────────────

    #[DataProvider('shapes')]
    public function test_the_emitted_declaration_judges_clean(string $source): void
    {
        $codebase = Codebase::fromString($source);
        $shape = NamespaceGraph::forCodebase($codebase)->currentShape();

        $this->assertSame([], $this->judge($codebase, $shape), 'the whole-shape declaration must judge clean');
    }

    #[DataProvider('shapes')]
    public function test_the_emitted_floor_judges_clean(string $source): void
    {
        // The smaller start has to hold too — it constrains less, never wrongly.
        $codebase = Codebase::fromString($source);
        $floor = NamespaceGraph::forCodebase($codebase)->floorShape();

        $this->assertSame([], $this->judge($codebase, $floor), 'the floor declaration must judge clean');
    }

    #[DataProvider('shapes')]
    public function test_the_emission_is_deterministic(string $source): void
    {
        // A config rewritten on every run is a config nobody can review in a diff.
        $first = NamespaceGraph::forCodebase(Codebase::fromString($source))->currentShape();
        $second = NamespaceGraph::forCodebase(Codebase::fromString($source))->currentShape();

        $this->assertSame($first, $second);
    }

    #[DataProvider('shapes')]
    public function test_no_emitted_layer_sits_inside_another(string $source): void
    {
        // The rule that makes the invariant possible: two layers where one contains the other would
        // cancel the containment that keeps their internal references legal.
        $layers = array_keys(NamespaceGraph::forCodebase(Codebase::fromString($source))->currentShape());
        $nested = [];

        foreach ($layers as $layer) {
            foreach ($layers as $other) {
                if ($layer !== $other && str_starts_with(strtolower($layer) . '\\', strtolower($other) . '\\')) {
                    $nested[] = "{$layer} is declared inside {$other}";
                }
            }
        }

        $this->assertSame([], $nested);
    }

    #[DataProvider('shapes')]
    public function test_the_floor_is_a_subset_of_the_shape_and_reaches_nothing(string $source): void
    {
        $graph = NamespaceGraph::forCodebase(Codebase::fromString($source));
        $shape = $graph->currentShape();
        $wrong = [];

        foreach ($graph->floorShape() as $layer => $mayUse) {
            if (! array_key_exists($layer, $shape)) {
                $wrong[] = "{$layer} is in the floor but not in the shape";
            }

            if ($mayUse !== []) {
                $wrong[] = "{$layer} is in the floor yet reaches " . implode(', ', $mayUse);
            }
        }

        $this->assertSame([], $wrong);
    }

    /**
     * Every shape the emission has to survive. Each is a whole program; the invariant above runs
     * all of them through emit → judge.
     *
     * @return array<string, array{0: string}>
     */
    public static function shapes(): array
    {
        return [
            'a plain two-layer stack' => ['<?php
                namespace App\Core { class Money {} }
                namespace App\Web { class Page { public function m(): \App\Core\Money { return new \App\Core\Money; } } }
            '],
            'a namespace nested in another, referencing it both ways' => ['<?php
                namespace App\Ai { class Agent { public function c(): \App\Ai\Contracts\Tool { return new \App\Ai\Contracts\Tool; } } }
                namespace App\Ai\Contracts { class Tool { public function a(): \App\Ai\Agent { return new \App\Ai\Agent; } } }
            '],
            'three levels of nesting' => ['<?php
                namespace App\A { class One { public function b(): \App\A\B\Two { return new \App\A\B\Two; } } }
                namespace App\A\B { class Two { public function c(): \App\A\B\C\Three { return new \App\A\B\C\Three; } } }
                namespace App\A\B\C { class Three { public function a(): \App\A\One { return new \App\A\One; } } }
            '],
            'siblings under one parent that reference each other' => ['<?php
                namespace App\Ui\Enums { enum Size { case Big; } }
                namespace App\Ui\Widgets { class Panel { public function s(): \App\Ui\Enums\Size { return \App\Ui\Enums\Size::Big; } } }
            '],
            'a parent and its child both referencing a third namespace' => ['<?php
                namespace App\Support { class Str { public function e(): \App\Shared\Helper { return new \App\Shared\Helper; } } }
                namespace App\Support\Enums { class Kind { public function e(): \App\Shared\Helper { return new \App\Shared\Helper; } } }
                namespace App\Shared { class Helper {} }
            '],
            'a mutual pair' => ['<?php
                namespace App\Billing { class Invoice { public function o(): \App\Orders\Order { return new \App\Orders\Order; } } }
                namespace App\Orders { class Order { public function i(): \App\Billing\Invoice { return new \App\Billing\Invoice; } } }
            '],
            'a three-hop cycle' => ['<?php
                namespace App\A { class One { public function g(): \App\B\Two { return new \App\B\Two; } } }
                namespace App\B { class Two { public function g(): \App\C\Three { return new \App\C\Three; } } }
                namespace App\C { class Three { public function g(): \App\A\One { return new \App\A\One; } } }
            '],
            'namespaces whose names share a prefix but not a segment' => ['<?php
                namespace App\Ui { class Panel { public function k(): \App\UiKit\Widget { return new \App\UiKit\Widget; } } }
                namespace App\UiKit { class Widget {} }
            '],
            'every kind of reference at once' => ['<?php
                namespace App\Base {
                    class Model {}
                    interface Contract {}
                    trait Helps {}
                    enum Kind { case One; }
                    class Failure extends \Exception {}
                    class Registry { const NAME = "r"; public static function all(): array { return []; } }
                }
                namespace App\App {
                    use App\Base\Helps;
                    class Thing extends \App\Base\Model implements \App\Base\Contract {
                        use Helps;
                        public \App\Base\Kind $kind = \App\Base\Kind::One;
                        public function run(\App\Base\Model $m): string {
                            try { \App\Base\Registry::all(); } catch (\App\Base\Failure $e) {}
                            return $m instanceof \App\Base\Model ? \App\Base\Registry::NAME : "";
                        }
                    }
                }
            '],
            'a reference that exists only as an import' => ['<?php
                namespace App\Core { class Money {} }
                namespace App\Web { use App\Core\Money; class Page {} }
            '],
            'only vendor references' => ['<?php
                namespace App\Orders { class Order extends \Illuminate\Database\Eloquent\Model {} }
            '],
            'classes at global scope' => ['<?php
                class Loose {}
                class Other { public function m(): Loose { return new Loose; } }
            '],
            'a namespace nothing references and that references nothing' => ['<?php
                namespace App\Core { class Money {} }
                namespace App\Island { class Alone {} }
                namespace App\Web { class Page { public function m(): \App\Core\Money { return new \App\Core\Money; } } }
            '],
            'a diamond' => ['<?php
                namespace App\Base { class Money {} }
                namespace App\Left { class L { public function m(): \App\Base\Money { return new \App\Base\Money; } } }
                namespace App\Right { class R { public function m(): \App\Base\Money { return new \App\Base\Money; } } }
                namespace App\Top { class T {
                    public function l(): \App\Left\L { return new \App\Left\L; }
                    public function r(): \App\Right\R { return new \App\Right\R; }
                } }
            '],
            'a nested pair PLUS a cross-tree cycle' => ['<?php
                namespace App\Ai { class Agent { public function t(): \App\Ai\Tools\Tool { return new \App\Ai\Tools\Tool; } } }
                namespace App\Ai\Tools { class Tool { public function s(): \App\Support\Str { return new \App\Support\Str; } } }
                namespace App\Support { class Str { public function a(): \App\Ai\Agent { return new \App\Ai\Agent; } } }
            '],
            'an empty program' => ['<?php namespace App\Nothing;'],
        ];
    }

    // ── the decisions that make it hold ──────────────────────────────────────────────────────

    public function test_only_the_outermost_of_a_nested_pair_is_declared(): void
    {
        $shape = $this->shape('<?php
            namespace App\Ai { class Agent { public function c(): \App\Ai\Contracts\Tool { return new \App\Ai\Contracts\Tool; } } }
            namespace App\Ai\Contracts { class Tool { public function a(): \App\Ai\Agent { return new \App\Ai\Agent; } } }
        ');

        $this->assertSame(['App\Ai' => []], $shape);
    }

    public function test_a_deep_nest_collapses_to_its_root(): void
    {
        $shape = $this->shape('<?php
            namespace App\A { class One { public function b(): \App\A\B\C\Three { return new \App\A\B\C\Three; } } }
            namespace App\A\B\C { class Three {} }
        ');

        $this->assertSame(['App\A' => []], $shape);
    }

    public function test_siblings_are_separate_layers_and_name_each_other(): void
    {
        $shape = $this->shape('<?php
            namespace App\Ui\Enums { enum Size { case Big; } }
            namespace App\Ui\Widgets { class Panel { public function s(): \App\Ui\Enums\Size { return \App\Ui\Enums\Size::Big; } } }
        ');

        $this->assertSame([
            'App\Ui\Enums' => [],
            'App\Ui\Widgets' => ['App\Ui\Enums'],
        ], $shape);
    }

    public function test_a_prefix_that_is_not_a_segment_stays_its_own_layer(): void
    {
        // `App\UiKit` is not inside `App\Ui`, so collapsing them would silently merge two trees.
        $shape = $this->shape('<?php
            namespace App\Ui { class Panel { public function k(): \App\UiKit\Widget { return new \App\UiKit\Widget; } } }
            namespace App\UiKit { class Widget {} }
        ');

        $this->assertSame(['App\UiKit' => [], 'App\Ui' => ['App\UiKit']], $shape);
    }

    public function test_a_mutual_pair_is_declared_as_it_stands(): void
    {
        // The layer rule is not the cycle rule. `layers` records the shape you HAVE; the cycle is
        // reported separately by namespace-cycle, which needs no declaration to be right.
        $shape = $this->shape('<?php
            namespace App\Billing { class Invoice { public function o(): \App\Orders\Order { return new \App\Orders\Order; } } }
            namespace App\Orders { class Order { public function i(): \App\Billing\Invoice { return new \App\Billing\Invoice; } } }
        ');

        $this->assertSame([
            'App\Billing' => ['App\Orders'],
            'App\Orders' => ['App\Billing'],
        ], $shape);
    }

    public function test_a_layer_is_declared_after_the_ones_it_uses(): void
    {
        // The declaration should read as the stack, bottom-up.
        $shape = $this->shape('<?php
            namespace App\Top { class T { public function m(): \App\Mid\M { return new \App\Mid\M; } } }
            namespace App\Mid { class M { public function b(): \App\Base\B { return new \App\Base\B; } } }
            namespace App\Base { class B {} }
        ');

        $this->assertSame(['App\Base', 'App\Mid', 'App\Top'], array_keys($shape));
    }

    public function test_vendor_targets_never_become_layers(): void
    {
        $this->assertSame([], $this->shape('<?php
            namespace App\Orders { class Order extends \Illuminate\Database\Eloquent\Model {} }
        '));
    }

    public function test_the_global_namespace_is_never_a_layer(): void
    {
        $this->assertSame([], $this->shape('<?php class Loose {} class Other { public function m(): Loose { return new Loose; } }'));
    }

    public function test_an_import_is_a_dependency_like_any_other(): void
    {
        $shape = $this->shape('<?php
            namespace App\Core { class Money {} }
            namespace App\Web { use App\Core\Money; class Page {} }
        ');

        $this->assertSame(['App\Core' => [], 'App\Web' => ['App\Core']], $shape);
    }

    public function test_the_floor_holds_only_what_reaches_nothing_of_yours(): void
    {
        $graph = NamespaceGraph::forCodebase(Codebase::fromString('<?php
            namespace App\Base { class B {} }
            namespace App\Mid { class M { public function b(): \App\Base\B { return new \App\Base\B; } } }
            namespace App\Top { class T { public function m(): \App\Mid\M { return new \App\Mid\M; } } }
        '));

        $this->assertSame(['App\Base' => []], $graph->floorShape());
    }

    public function test_declaring_a_parent_in_may_use_permits_its_children(): void
    {
        // `mayUse` names namespaces, and containment applies there too — so permitting `App\Base`
        // permits everything under it without listing each child.
        $codebase = Codebase::fromString('<?php
            namespace App\Base\Deep { class D {} }
            namespace App\Top { class T { public function d(): \App\Base\Deep\D { return new \App\Base\Deep\D; } } }
        ');

        $detector = new NamespaceDependencyDetector()
            ->layer('App\Base')
            ->layer('App\Top', mayUse: ['App\Base']);

        $this->assertSame([], $detector->find($codebase));
    }

    /**
     * @return array<string, list<string>>
     */
    private function shape(string $source): array
    {
        return NamespaceGraph::forCodebase(Codebase::fromString($source))->currentShape();
    }

    /**
     * Run the detector configured with $shape, returning the scopes it flags.
     *
     * @param  array<string, list<string>>  $shape
     * @return list<string>
     */
    private function judge(Codebase $codebase, array $shape): array
    {
        $detector = new NamespaceDependencyDetector();

        foreach ($shape as $layer => $mayUse) {
            $detector->layer($layer, $mayUse);
        }

        return array_map(static fn (NodeMatch $m): string => $m->location() . ' ' . $m->scope(), $detector->find($codebase));
    }
}
