<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\Backend\NamespaceDependencyDetector;
use PHPUnit\Framework\TestCase;

final class NamespaceDependencyDetectorTest extends TestCase
{
    public function test_undeclared_layers_leave_the_rule_inert(): void
    {
        // Only the project knows its own stack: with nothing declared there is no direction to
        // break, so the rule must find nothing at all rather than guess a layering.
        $this->assertSame([], new NamespaceDependencyDetector()->find(Codebase::fromString($this->stack())));
    }

    public function test_flags_a_layer_reaching_back_up_the_stack(): void
    {
        $hits = $this->judge($this->stack());

        $this->assertSame(['App\Ui\Elements\Button::wrap'], $this->scopes($hits));
    }

    public function test_reaching_down_and_sideways_within_the_layer_is_clean(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Ui\Elements { class Icon {} class Button { public function icon(): Icon { return new Icon; } } }
        namespace App\Ui\Shared { use App\Ui\Elements\Button; class Card { public function __construct(private Button $button) {} } }
        PHP;

        $this->assertSame([], $this->judge($code));
    }

    public function test_an_undeclared_namespace_is_always_allowed(): void
    {
        // The framework, vendor, and any namespace the project never declared stay usable in both
        // directions — the declaration constrains the layering you chose, it never invents one.
        $code = <<<'PHP'
        <?php
        namespace App\Ui\Elements { use Illuminate\Support\Str; class Button { public function slug(): string { return Str::slug('x'); } } }
        namespace App\Http { use App\Ui\Elements\Button; class Controller { public function show(Button $b): void {} } }
        PHP;

        $this->assertSame([], $this->judge($code));
    }

    public function test_every_kind_of_reference_is_the_same_arrow(): void
    {
        // extends, a return type, a static call and a catch type are one dependency each — the rule
        // cannot be dodged by spelling the name somewhere other than an import.
        $code = <<<'PHP'
        <?php
        namespace App\Ui\Shared { class Card {} class CardFailed extends \Exception {} class Registry { public static function all(): array { return []; } } }
        namespace App\Ui\Elements {
            class Extending extends \App\Ui\Shared\Card {}
            class Returning { public function card(): \App\Ui\Shared\Card { return new \App\Ui\Shared\Card; } }
            class Calling { public function all(): array { return \App\Ui\Shared\Registry::all(); } }
            class Catching { public function run(): void { try {} catch (\App\Ui\Shared\CardFailed $e) {} } }
        }
        PHP;

        $this->assertSame([
            'App\Ui\Elements\Calling::all',
            'App\Ui\Elements\Catching::run',
            'App\Ui\Elements\Extending',
            'App\Ui\Elements\Returning::card',
        ], $this->scopes($this->judge($code)));
    }

    public function test_one_finding_per_referrer_and_target(): void
    {
        // A breach is a fact about the PAIR of declarations, not about how many times the referring
        // one spells the name — two methods reaching for the same Card is one arrow.
        $code = <<<'PHP'
        <?php
        namespace App\Ui\Shared { class Card {} }
        namespace App\Ui\Elements {
            use App\Ui\Shared\Card;
            class Button {
                public function a(): Card { return new Card; }
                public function b(): Card { return new Card; }
            }
        }
        PHP;

        $this->assertCount(1, $this->judge($code));
    }

    public function test_the_most_specific_declared_layer_wins(): void
    {
        // `App\Ui` and `App\Ui\Elements` both match a Button; the NARROWER claim is the one meant,
        // so the Button is judged as an Element and its reach up into Shared still breaches.
        $code = <<<'PHP'
        <?php
        namespace App\Ui\Shared { class Card {} }
        namespace App\Ui\Elements { use App\Ui\Shared\Card; class Button { public function card(): Card { return new Card; } } }
        PHP;

        $detector = new NamespaceDependencyDetector()
            ->layer('App\Ui', mayUse: ['App\Ui\Shared'])
            ->layer('App\Ui\Elements');

        $this->assertSame(['App\Ui\Elements\Button::card'], $this->scopes($detector->find(Codebase::fromString($code))));
    }

    public function test_a_name_that_resolves_to_no_class_here_is_not_a_dependency(): void
    {
        // Found on a real tree: an import left behind by a class that moved away still LOOKS like it
        // points into a declared layer, but there is nothing there to depend on. Judging it also made
        // the rule disagree with the graph that proposes declarations, so `layers` could emit a
        // config that failed on the very next judge.
        $code = <<<'PHP'
        <?php
        namespace App\Ui\Shared { class Card {} }
        namespace App\Ui\Elements { class Button { public function gone(): \App\Ui\Shared\Deleted { return new \App\Ui\Shared\Deleted; } } }
        PHP;

        $this->assertSame([], $this->judge($code));
    }

    public function test_a_vendor_class_under_a_declared_prefix_is_not_a_dependency(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Ui\Shared { class Card {} }
        namespace App\Ui\Elements { class Button { public function v(): \App\Ui\Shared\Vendor\Thing { return new \App\Ui\Shared\Vendor\Thing; } } }
        PHP;

        $this->assertSame([], $this->judge($code));
    }

    public function test_a_sibling_namespace_is_not_within_a_layer_that_merely_shares_a_prefix(): void
    {
        // `App\UiKit` is NOT inside `App\Ui` — a layer is matched on segment boundaries, never on a
        // bare string prefix, or a neighbouring namespace would silently inherit its permissions.
        $code = <<<'PHP'
        <?php
        namespace App\Ui\Shared { class Card {} }
        namespace App\UiKit { use App\Ui\Shared\Card; class Widget { public function card(): Card { return new Card; } } }
        PHP;

        $this->assertSame([], $this->judge($code));
    }

    /**
     * The declared stack the tests judge against: Elements are the primitives (themselves only),
     * Shared is built from them.
     */
    private function stack(): string
    {
        return <<<'PHP'
        <?php
        namespace App\Ui\Shared { class Card {} }
        namespace App\Ui\Elements {
            use App\Ui\Shared\Card;
            class Button { public function wrap(): Card { return new Card; } }
        }
        PHP;
    }

    /**
     * @return list<NodeMatch>
     */
    private function judge(string $code): array
    {
        return new NamespaceDependencyDetector()
            ->layer('App\Ui\Elements')
            ->layer('App\Ui\Shared', mayUse: ['App\Ui\Elements'])
            ->find(Codebase::fromString($code));
    }

    /**
     * @param  list<NodeMatch>  $hits
     * @return list<string>
     */
    private function scopes(array $hits): array
    {
        $scopes = array_map(static fn (NodeMatch $m): string => $m->scope(), $hits);
        sort($scopes);

        return $scopes;
    }
}
