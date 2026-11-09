package rule_test

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func locations(matches []engine.Match) []string {
	var found []string
	for _, match := range matches {
		found = append(found, match.Location())
	}

	slices.Sort(found)

	return found
}

func TestTheOrderARuleIsWrittenInChangesNothingItFinds(t *testing.T) {
	codebase := phpCodebase(t, "Actions.php", actions)

	dearFirst := parsed(t, "DearFirst", `{"select": "function", "where": [{"unused": false}, {"inside": {"is": "type-declaration"}}, {"nameLike": "h*"}, {"name": "handle"}]}`)
	cheapFirst := parsed(t, "CheapFirst", `{"select": "function", "where": [{"name": "handle"}, {"nameLike": "h*"}, {"inside": {"is": "type-declaration"}}, {"unused": false}]}`)

	one, other := locations(dearFirst.Find(codebase)), locations(cheapFirst.Find(codebase))
	if !slices.Equal(one, other) {
		t.Errorf("written in another order the rule found %v, and %v", one, other)
	}
}

func TestAChangedRuleHasANewFingerprint(t *testing.T) {
	one := parsed(t, "Rule", `{"select": "function", "where": [{"name": "handle"}]}`)
	same := parsed(t, "Rule", `{"select": "function", "where": [{"name": "handle"}]}`)
	changed := parsed(t, "Rule", `{"select": "function", "where": [{"name": "run"}]}`)

	if one.Fingerprint() != same.Fingerprint() {
		t.Error("the same rule read twice should have one fingerprint")
	}

	if one.Fingerprint() == changed.Fingerprint() {
		t.Error("a changed rule kept its fingerprint, so it would answer from the old memory")
	}
}

func TestChangedLayersChangeTheFingerprint(t *testing.T) {
	read := parsed(t, "Layered", `{"select": "function", "where": [{"name": "handle"}]}`)

	one := read.WithLayers(map[string][]string{"domain": {"App\\Domain"}})
	other := read.WithLayers(map[string][]string{"domain": {"App\\Core"}})

	if one.Fingerprint() == other.Fingerprint() {
		t.Error("rules given different layers share a fingerprint")
	}
}
