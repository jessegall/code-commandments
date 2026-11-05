package rule

import (
	"reflect"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
	"github.com/jessegall/code-commandments/skill"
)

// TestEveryCheckSaysWhetherItIsAnsweredFromItsFile holds the two lists to the vocabulary. A check neither names
// is read as reaching beyond its file, which is the safe way to be wrong and a silent way to be right: the list
// is what a reader goes to, so a check missing from both would read as a decision nobody made.
func TestEveryCheckSaysWhetherItIsAnsweredFromItsFile(t *testing.T) {
	written := jsonKeys(reflect.TypeFor[Step]())

	for _, key := range written {
		fromTheFile := slices.Contains(answeredFromTheFile, key)
		beyond := slices.Contains(readAcrossFiles, key)

		if fromTheFile && beyond {
			t.Errorf("%q is in both lists; a check is answered from its file or it is not", key)
		}

		if !fromTheFile && !beyond {
			t.Errorf("%q is in neither list: say whether it can be answered from the file it reports in", key)
		}
	}

	for _, key := range append(slices.Clone(answeredFromTheFile), readAcrossFiles...) {
		if !slices.Contains(written, key) {
			t.Errorf("the lists name %q, which no step writes", key)
		}
	}
}

func TestARuleSaysWhetherItReadsBeyondTheFileItReportsIn(t *testing.T) {
	for written, across := range map[string]bool{
		// A layout rule reads its own file: the folder it sits in, what it declares and what it is called.
		`{"select": "type-declaration", "where": [{"fileMatches": "/migrations/"}, {"nameLike": "*Migration"}]}`: false,
		// Its direct parent is written in its own header; the chain above that is not.
		`{"select": "type-declaration", "where": [{"extends": "Migration"}]}`:    false,
		`{"select": "type-declaration", "where": [{"extendsAny": "Migration"}]}`: true,
		// A check buried in a holder, a negation or a count reaches as far as the check itself does.
		`{"select": "type-declaration", "where": [{"not": {"extendsAny": "Migration"}}]}`:                    true,
		`{"select": "type-declaration", "where": [{"any": [{"name": "x"}, {"implements": "Lifecycle"}]}]}`:    true,
		`{"select": "function", "where": [{"count": {"descendant": {"unused": true}, "atLeast": 1}}]}`:        true,
		`{"select": "type-declaration", "where": [{"members": {"is": "function", "atLeast": 2}}]}`:            false,
		`{"select": "type-declaration", "where": [{"members": {"inherited": true, "atLeast": 20}}]}`:          true,
		// A target that steps onto a declaration lands wherever that declaration was written.
		`{"select": "member-access", "where": [{"name": "total", "of": "receiver"}]}`:                                false,
		`{"select": "member-access", "where": [{"file": "app/Models/**", "of": "declaredBy"}]}`:                      true,
		// A reject is a check like any other: the rule still has to read what it rejects by.
		`{"select": "type-declaration", "where": [{"nameLike": "*Migration"}], "reject": [{"callers": {"atMost": 0}}]}`: true,
	} {
		rule, err := Parse("LayoutDetector", []byte(`{"engine": "python", "sin": {"name": "helper-in-a-kind-folder", "description": "A class sits in a folder of another kind", "skill": "python/absence"}, "find": `+written+`}`), skill.Slugged)
		if err != nil {
			t.Fatalf("%s: %v", written, err)
		}

		if got := rule.ReadsAcrossFiles(); got != across {
			t.Errorf("%s: reads across files %v, want %v", written, got, across)
		}

		if got := catalog.IsCrossFile(rule); got != across {
			t.Errorf("%s: the catalog reads it as cross-file %v, want %v", written, got, across)
		}
	}
}

// TestAChainOfParentsIsAnsweredWrongWhenTheFileIsReadAlone measures what the lists above claim, the way the
// fixture harness measures a shipped rule: the rule's answers about a file differ when it is shown that file
// alone, so a per-file check and a scoped run must not show it one. This is issue 824 as the consumer hit it — a
// migration whose base class sits two files away, reported as a stranger in the migrations folder because the
// edited file was all the rule was given.
func TestAChainOfParentsIsAnsweredWrongWhenTheFileIsReadAlone(t *testing.T) {
	codebase := pythontest.FromSource(t, map[string]string{
		"shop/__init__.py": "",
		"shop/migration.py": `class Migration:
    number = 0
`,
		"shop/rebuild_migration.py": `from shop.migration import Migration


class RebuildMigration(Migration):
    statements: list[str] = []
`,
		"shop/migrations/__init__.py": "",
		"shop/migrations/m004_drop_fades.py": `from shop.rebuild_migration import RebuildMigration


class DropFadesMigration(RebuildMigration):
    number = 4
`,
	})

	rule, err := Parse("HelperInAKindFolderDetector", []byte(`{"engine": "python", "sin": {"name": "helper-in-a-kind-folder", "description": "A class sits among the classes of a kind folder it is not", "skill": "python/absence"}, "find": {"select": "type-declaration", "where": [{"fileMatches": "/migrations/[^/]+\\.py$"}, {"not": {"extendsAny": "Migration"}}]}}`), skill.Slugged)
	if err != nil {
		t.Fatal(err)
	}

	if found := rule.Find(codebase); len(found) > 0 {
		t.Fatalf("given every file, the rule reports %v; the class extends Migration through RebuildMigration", locations(found))
	}

	alone, found := fileNamed(codebase, "m004_drop_fades.py")
	if !found {
		t.Fatal("the scan read no m004_drop_fades.py")
	}

	if found := rule.Find(alone); len(found) == 0 {
		t.Fatal("given the file alone the rule answers the same, so this is no longer the measurement it claims to be")
	}

	if !rule.ReadsAcrossFiles() {
		t.Error("the rule answers differently when shown one file and does not say it reads across files")
	}
}

// fileNamed is the codebase of one file of this codebase alone, named by the tail of its path.
func fileNamed(codebase *engine.Codebase, name string) (*engine.Codebase, bool) {
	for _, file := range codebase.Files() {
		if strings.HasSuffix(file.File.Path, name) {
			return file.Alone(), true
		}
	}

	return nil, false
}

// locations are the places a run found, for a failure to name.
func locations(found []engine.Match) []string {
	var places []string
	for _, match := range found {
		places = append(places, match.Location())
	}

	return places
}
