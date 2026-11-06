package rule_test

import (
	"fmt"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/rule"
)

func written(engine, name, query string) []byte {
	return []byte(`{"engine": "` + engine + `", "sin": {"name": "` + name + `", "description": "a sin the test writes", "skill": "php/laravel-idioms"}, "find": ` + query + `}`)
}

func parsed(t *testing.T, name, query string) rule.Rule {
	t.Helper()
	read, err := rule.Parse(name, written("backend", "composed-sin", query), shipped)
	if err != nil {
		t.Fatalf("%s: %v", name, err)
	}

	return read
}

func TestARuleIsBuiltFromAnotherRule(t *testing.T) {
	codebase := phpCodebase(t, "Actions.php", actions)
	composed, refused := rule.Composed([]rule.Rule{
		parsed(t, "ActionClass", `{"select": "type-declaration", "where": [{"uses": "AsAction"}]}`),
		parsed(t, "ActionHandler", `{"select": "function", "where": [{"name": "handle"}, {"rule": "ActionClass", "of": "parent"}]}`),
		parsed(t, "PublicActionClass", `{"from": "ActionClass", "where": [{"members": {"all": [{"name": "handle"}, {"hasModifier": "public"}], "atLeast": 1}}]}`),
		parsed(t, "NotAnActionClass", `{"select": "type-declaration", "reject": [{"rule": "ActionClass"}]}`),
	})
	if len(refused) > 0 {
		t.Fatalf("the rules are refused: %v", refused)
	}

	want := map[string]string{"ActionClass": "[11]", "ActionHandler": "[15]", "PublicActionClass": "[11]", "NotAnActionClass": "[7 9 18 23 30 37]"}
	for _, one := range composed {
		if got := fmt.Sprint(lines(one.Find(codebase))); got != want[one.Name()] {
			t.Errorf("%s found %s, want %s", one.Name(), got, want[one.Name()])
		}
	}
}

func TestARuleBuiltFromOneThatIsNotThereIsRefused(t *testing.T) {
	_, refused := rule.Composed([]rule.Rule{
		parsed(t, "NamesNobody", `{"select": "call", "where": [{"rule": "NoSuchRule"}]}`),
	})
	if len(refused) != 1 || !strings.Contains(refused[0].Error(), `no rule is called "NoSuchRule"`) {
		t.Errorf("a rule naming one nothing declares is refused with %v", refused)
	}

	kept, cyclic := rule.Composed([]rule.Rule{
		parsed(t, "First", `{"from": "Second", "where": [{"name": "handle"}]}`),
		parsed(t, "Second", `{"select": "call", "where": [{"rule": "First"}]}`),
	})
	if len(cyclic) == 0 || !strings.Contains(cyclic[0].Error(), "cannot be built from itself") {
		t.Fatalf("a chain that comes back to itself is refused with %v", cyclic)
	}

	for _, one := range kept {
		t.Errorf("%s is in the chain that was refused, yet it is kept", one.Name())
	}
}

func TestARuleIsBuiltOnlyFromItsOwnEngine(t *testing.T) {
	other, err := rule.Parse("PythonRule", written("python", "python-sin", `{"select": "call", "where": [{"name": "handle"}]}`), shipped)
	if err != nil {
		t.Fatalf("the python rule is not read: %v", err)
	}

	_, refused := rule.Composed([]rule.Rule{other, parsed(t, "PhpRule", `{"from": "PythonRule"}`)})
	if len(refused) != 1 || !strings.Contains(refused[0].Error(), "judges the python engine") {
		t.Errorf("a rule built from another engine's rule is refused with %v", refused)
	}
}

func TestARuleNamesOneOfSelectAndFrom(t *testing.T) {
	for _, query := range []string{`{"select": "call", "from": "ActionClass"}`, `{"where": [{"name": "handle"}]}`} {
		if _, err := rule.Parse("Neither", written("backend", "neither", query), shipped); err == nil {
			t.Errorf("%s is read, yet it names neither one of select and from, or both", query)
		}
	}
}

func TestARuleCombinesAWholeProgramReadingWithItsOwnChecks(t *testing.T) {
	codebase := phpCodebase(t, "Actions.php", actions)
	for query, want := range map[string]string{
		`{"select": "function", "where": [{"name": "handle"}, {"callers": {"atLeast": 1}}]}`:            "[15 20]",
		`{"select": "function", "where": [{"name": "handle"}, {"callers": {"atMost": 0}}]}`:             "[]",
		`{"select": "function", "where": [{"name": "render"}, {"callers": {"atMost": 0}}]}`:             "[27]",
		`{"select": "function", "where": [{"all": [{"name": "handle"}, {"callers": {"atLeast": 2}}]}]}`: "[15 20]",
		`{"select": "function", "where": [{"php": "divergentTwin"}]}`:                                   "[]",
	} {
		found, err := rule.Parse("Reading", written("backend", "reading-sin", query), shipped)
		if err != nil {
			t.Fatalf("%s: %v", query, err)
		}

		if got := fmt.Sprint(lines(found.Find(codebase))); got != want {
			t.Errorf("%s: found %s, want %s", query, got, want)
		}
	}
}
