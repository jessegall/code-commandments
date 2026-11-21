package rule

import "testing"

func TestAnEditedRuleOrParameterChangesWhatItsAnswersStandOn(t *testing.T) {
	declared := `"parameters": {"weight": {"type": "number", "default": 2, "description": "How heavy."}}, `
	first := parsed(t, written(declared+`"find": {"select": "if", "as": "b", "where": [">=", ["count", "$b.then.statements"], "$param.weight"]}`))
	edited := parsed(t, written(declared+`"find": {"select": "if", "as": "b", "where": [">", ["count", "$b.then.statements"], "$param.weight"]}`))
	if first.Fingerprint() == edited.Fingerprint() {
		t.Errorf("an edited find kept the fingerprint, so the old rule's answers would be served")
	}
	heavier, err := first.WithParameter("weight", 5)
	if err != nil {
		t.Fatal(err)
	}
	if first.Fingerprint() == heavier.Fingerprint() {
		t.Errorf("a parameter set by configure kept the fingerprint")
	}
	if again := parsed(t, written(declared+`"find": {"select": "if", "as": "b", "where": [">=", ["count", "$b.then.statements"], "$param.weight"]}`)); again.Fingerprint() != first.Fingerprint() {
		t.Errorf("the same rule read twice has two fingerprints, so nothing it answered is remembered")
	}
}

func TestARuleReadingTheWholeProgramStandsOnTheTree(t *testing.T) {
	ownFile := parsed(t, written(`"find": {"select": "if", "as": "b", "where": ["exists", "$b.else"]}`))
	referred := parsed(t, written(`"find": {"select": "function", "as": "f", "where": ["=", ["count", ["referrers", "$f"]], 0]}`))
	resolved := parsed(t, written(`"find": {"select": "access", "as": "a", "where": ["exists", ["typeDeclaration", "$a.subject.resolved.name"]]}`))
	grouped := parsed(t, written(`"find": {"from": "function", "as": "f", "pipeline": [{"project": {"anchor": "$f", "key": ["fingerprint", "$f", "exact"]}}, {"groupBy": "$key"}, {"having": [">=", ["count", "$rows"], 2]}, {"expand": "$rows"}, {"emit": "$row.anchor", "group": "$key"}]}`))
	for name, read := range map[string]Rule{"a rule over its own file": ownFile, "a rule over what a file imports": resolved} {
		if read.StandsOnTheTree() {
			t.Errorf("%s stands on the tree, so any change anywhere asks it again", name)
		}
	}
	for name, read := range map[string]Rule{"a rule asking who refers": referred, "a rule grouping rows across files": grouped} {
		if !read.StandsOnTheTree() {
			t.Errorf("%s does not stand on the tree, so a change in another file serves a stale answer", name)
		}
	}
	if ownFile.ReadsAcrossFiles() || !resolved.ReadsAcrossFiles() {
		t.Errorf("an own-file rule reads across files %v, a resolving one %v", ownFile.ReadsAcrossFiles(), resolved.ReadsAcrossFiles())
	}
}
