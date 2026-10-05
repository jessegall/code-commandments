package rule_test

import (
	"fmt"
	"testing"

	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
	"github.com/jessegall/code-commandments/rule"
)

// actions is a project's action classes and their callers, as transportklok-workspace wrote them while porting its
// own detectors to rules: a handle() on an action runs it by hand.
const actions = `<?php

namespace Sample;

use Lorisleiva\Actions\Concerns\AsAction;

abstract class Repository {}

final class AgreementRepository extends Repository {}

final class PayAction
{
    use AsAction;

    public function handle(int $id): void {}
}

final class PlainThing
{
    public function handle(): void {}
}

abstract class Page
{
    protected string $template = 'page';

    public function render(): string { return $this->template; }
}

final class InvoicePage extends Page
{
    public function store(): void {}

    private function template(): void {}
}

final class Caller
{
    public function __construct(private PayAction $pay, private PlainThing $plain) {}

    public function run(PayAction $typed, PlainThing $other): void
    {
        $this->pay->handle(1);
        $typed->handle(2);
        (new PayAction)->handle(3);
        $this->plain->handle();
        $other->handle();
        $repository = new AgreementRepository();
        $named = AgreementRepository::class;
    }
}
`

// TestARuleJudgesTheClassANodeIsAbout holds the class checks to the class a node names, constructs or holds a value
// of, uses to a class's traits, members to what its parents declare, and all to one node passing every step.
func TestARuleJudgesTheClassANodeIsAbout(t *testing.T) {
	codebase := phpCodebase(t, "Actions.php", actions)
	for query, want := range map[string]string{
		`{"select": "call", "where": [{"name": "handle"}, {"uses": "AsAction", "of": "child:var"}]}`:                          "[43 44 45]",
		`{"select": "call", "where": [{"name": "handle"}, {"implements": "Countable", "of": "child:var"}]}`:                   "[]",
		`{"select": "construction", "where": [{"extendsAny": "Repository"}]}`:                                                 "[48]",
		`{"select": "construction", "where": [{"extends": "Repository"}]}`:                                                    "[48]",
		`{"select": "type-declaration", "where": [{"uses": "AsAction"}]}`:                                                     "[11]",
		`{"select": "type-declaration", "where": [{"members": {"name": "template", "atLeast": 1}}]}`:                         "[23 30]",
		`{"select": "type-declaration", "where": [{"members": {"all": [{"name": "template"}, {"kind": "Stmt_Property"}], "atLeast": 1}}]}`: "[23]",
		`{"select": "type-declaration", "where": [{"members": {"all": [{"name": "template"}, {"kind": "Stmt_Property"}], "atLeast": 1, "inherited": true}}]}`: "[23 30]",
		`{"select": "type-declaration", "where": [{"members": {"all": [{"name": "store"}, {"hasModifier": "public"}], "atLeast": 1}}]}`: "[30]",
	} {
		written := `{"engine": "backend", "sin": {"name": "action-run-by-hand", "description": "an action's handle called by hand", "skill": "backend/laravel-idioms"}, "find": ` + query + `}`
		found, err := rule.Parse("ActionRunnerInvocationDetector", []byte(written), shipped)
		if err != nil {
			t.Fatalf("%s: %v", query, err)
		}
		if got := fmt.Sprint(lines(found.Find(codebase))); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

// TestARuleReadsAnAttributeATemplateElementWrites holds hasAttribute to an attribute written plain or bound.
func TestARuleReadsAnAttributeATemplateElementWrites(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"src/Form.vue": "<template>\n  <form>\n    <input data-dusk=\"name\" />\n    <input :data-dusk=\"field\" />\n    <input name=\"plain\" />\n  </form>\n</template>\n\n<script setup lang=\"ts\">\nconst field = 'email'\n</script>\n"})
	written := `{"engine": "frontend", "sin": {"name": "untested-input", "description": "an input with no dusk selector", "skill": "frontend/vue-components"}, "find": {"select": "kind:Element", "where": [{"name": "input"}], "reject": [{"hasAttribute": "data-dusk"}]}}`
	found, err := rule.Parse("AppEngineeringFormDetector", []byte(written), shipped)
	if err != nil {
		t.Fatal(err)
	}
	if got := fmt.Sprint(lines(found.Find(codebase))); got != "[5]" {
		t.Errorf("the inputs without a dusk selector are at %s", got)
	}
}
