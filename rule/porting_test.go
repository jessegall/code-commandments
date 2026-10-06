package rule_test

import (
	"fmt"
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"

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

// TestARuleMatchesClassesByPatternAndFilesByTheirNeighbours holds the second round of transportklok's port: a
// receiver's class by a glob or as that exact class, receivers counted once each, a file's neighbour, and globs
// with character classes.
func TestARuleMatchesClassesByPatternAndFilesByTheirNeighbours(t *testing.T) {
	root := t.TempDir()
	for path, source := range map[string]string{
		"app/Billing.php": `<?php

namespace App;

class FormRequest {}

final class RequestState extends FormRequest {}

final class InvoiceService
{
    public function create(): void {}

    public function update(): void {}
}

final class LedgerService
{
    public function create(): void {}
}

final class Pages
{
    public function __construct(private InvoiceService $invoices, private LedgerService $ledger) {}

    public function one(RequestState $state, FormRequest $plain): void
    {
        $this->invoices->create();
        $this->invoices->update();
        $state->validate();
        $plain->validate();
    }

    public function two(): void
    {
        $this->invoices->create();
        $this->ledger->create();
    }
}
`,
		"migrations/v12/AddTotals.php": "<?php\n\nnamespace Migrations\\V12;\n\nfinal class AddTotals {}\n",
		"migrations/v12/v12.php":        "<?php\n\nnamespace Migrations\\V12;\n\nfinal class Helper {}\n",
		"migrations/vnext/Draft.php":    "<?php\n\nnamespace Migrations\\Next;\n\nfinal class Draft {}\n",
	} {
		if err := os.MkdirAll(filepath.Dir(filepath.Join(root, path)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(root, path), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	codebase, err := scan.Walk([]string{root}, source.Excluded{}).Load()
	if err != nil {
		t.Fatal(err)
	}
	for query, want := range map[string]string{
		`{"select": "call", "where": [{"resolvesLike": "*Service", "of": "child:var"}]}`:                                      "[27 28 35 36]",
		`{"select": "call", "where": [{"name": "validate"}, {"resolves": "App\\RequestState", "of": "child:var"}]}`:             "[29]",
		`{"select": "call", "where": [{"name": "validate"}, {"extendsAny": "*Request", "of": "child:var"}]}`:                    "[29]",
		`{"select": "function", "where": [{"count": {"descendant": {"all": [{"is": "call"}, {"nameIn": ["create", "update"]}]}, "distinct": "child:var", "atMost": 1}}, {"count": {"descendant": {"all": [{"is": "call"}, {"nameIn": ["create", "update"]}]}, "atLeast": 1}}]}`: "[25]",
		`{"select": "type-declaration", "where": [{"file": "**/migrations/v[0-9]*/*"}, {"sibling": "{folder}.php"}]}`:          "[5]",
	} {
		written := `{"engine": "backend", "sin": {"name": "ported", "description": "a ported rule", "skill": "backend/laravel-idioms"}, "find": ` + query + `}`
		found, err := rule.Parse("PortedDetector", []byte(written), shipped)
		if err != nil {
			t.Fatalf("%s: %v", query, err)
		}
		if got := fmt.Sprint(lines(found.Find(codebase))); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}

// TestARuleReadsAParentFoldersFileAndTheClassItself holds the third round of transportklok's port: a helper's
// migration one folder up, and a class that is the type or extends it.
func TestARuleReadsAParentFoldersFileAndTheClassItself(t *testing.T) {
	root := t.TempDir()
	for path, source := range map[string]string{
		"migrations/fresh/orders.php":        "<?php\n\nnamespace Migrations;\n\nfinal class Orders {}\n",
		"migrations/fresh/orders/Helper.php": "<?php\n\nnamespace Migrations\\Orders;\n\nfinal class Helper {}\n",
		"migrations/fresh/stray/Helper.php":  "<?php\n\nnamespace Migrations\\Stray;\n\nfinal class Helper {}\n",
		"app/Requests.php":                   "<?php\n\nnamespace App;\n\nclass Request {}\n\nfinal class StoreRequest extends Request {}\n\nfinal class Other {}\n",
	} {
		if err := os.MkdirAll(filepath.Dir(filepath.Join(root, path)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(root, path), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	codebase, err := scan.Walk([]string{root}, source.Excluded{}).Load()
	if err != nil {
		t.Fatal(err)
	}
	for query, want := range map[string]string{
		`{"select": "type-declaration", "where": [{"file": "**/fresh/*/*"}, {"sibling": "../{folder}.php"}]}`: "[5]",
		`{"select": "type-declaration", "where": [{"isA": "App\\Request"}]}`:                                   "[5 7]",
		`{"select": "type-declaration", "where": [{"extendsAny": "App\\Request"}]}`:                            "[7]",
	} {
		written := `{"engine": "backend", "sin": {"name": "ported", "description": "a ported rule", "skill": "backend/laravel-idioms"}, "find": ` + query + `}`
		found, err := rule.Parse("PortedDetector", []byte(written), shipped)
		if err != nil {
			t.Fatalf("%s: %v", query, err)
		}
		var at []string
		for _, match := range found.Find(codebase) {
			at = append(at, fmt.Sprintf("%s:%d", filepath.Base(match.File()), match.Line()))
		}
		if got := fmt.Sprint(lines(found.Find(codebase))); got != want {
			t.Errorf("%s: found %v, want lines %s", query, at, want)
		}
	}
}

// TestARuleReadsTheTextATemplateShows holds a template's text to the words a reader sees, its whitespace collapsed,
// so a rule judges a paragraph or a heading as it judges an attribute's value; an interpolation stays its own node.
func TestARuleReadsTheTextATemplateShows(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"src/DumpStart.vue": "<template>\n  <section>\n    <h2>Start a dump</h2>\n    <p class=\"dump-context\">Mixed is fine.\n      The agent sorts it {{ later }}.</p>\n  </section>\n</template>\n\n<script setup lang=\"ts\">\nconst later = 'later'\n</script>\n"})
	written := `{"engine": "frontend", "sin": {"name": "plain-text", "description": "viewer text", "skill": "frontend/vue-components"}, "find": {"select": "kind:Text", "where": [{"textMatches": "^Mixed is fine\\. The agent sorts it$"}]}}`
	found, err := rule.Parse("PlainViewerTextDetector", []byte(written), shipped)
	if err != nil {
		t.Fatal(err)
	}
	if got := fmt.Sprint(lines(found.Find(codebase))); got != "[4]" {
		t.Errorf("the paragraph's text is found at %s", got)
	}
}
