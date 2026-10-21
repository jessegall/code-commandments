package rule_test

import (
	"fmt"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/rule"
)

// written is a rule file of the engine, the sin and the query given.
func written(engine, name, query string) []byte {
	return []byte(`{"engine": "` + engine + `", "sin": {"name": "` + name + `", "description": "a sin the test writes", "skill": "backend/laravel-idioms"}, "find": ` + query + `}`)
}

// parsed is the rule the query makes, failing the test when it cannot be read.
func parsed(t *testing.T, name, query string) rule.Rule {
	t.Helper()
	read, err := rule.Parse(name, written("backend", "composed-sin", query), shipped)
	if err != nil {
		t.Fatalf("%s: %v", name, err)
	}

	return read
}

// TestARuleIsBuiltFromAnotherRule holds a rule naming another: as a check on the node it is on, and as the
// population it narrows, which is one rule found inside another.
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

// TestARuleBuiltFromOneThatIsNotThereIsRefused holds the two ways a reference cannot be followed: a name nothing
// declares, and a chain that comes back to itself. Both are refused when the rules are read, so judging never
// meets one.
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

// TestARuleIsBuiltOnlyFromItsOwnEngine holds a reference to one language's tree: a rule reads the nodes of its own
// engine, so a rule of another engine is no part it can be built from.
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

// TestARuleNamesOneOfSelectAndFrom holds what a rule finds: the nodes it selects, or the ones another rule found,
// never both and never neither.
func TestARuleNamesOneOfSelectAndFrom(t *testing.T) {
	for _, query := range []string{`{"select": "call", "from": "ActionClass"}`, `{"where": [{"name": "handle"}]}`} {
		if _, err := rule.Parse("Neither", written("backend", "neither", query), shipped); err == nil {
			t.Errorf("%s is read, yet it names neither one of select and from, or both", query)
		}
	}
}
