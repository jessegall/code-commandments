package rules

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func written(t *testing.T, files map[string]string) string {
	t.Helper()

	root := t.TempDir()
	for path, contents := range files {
		file := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(file, []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

func neutral(sin, engine string) string {
	return `{"engine": "` + engine + `", "sin": {"name": "` + sin + `", "description": "a loop whose body is one branch", "skill": "php/absence"},
		"find": {"select": "loop", "where": [{"body": {"is": "branch"}}]}}`
}

func TestMigrateMergesTheCopiesThatDifferOnlyInTheirEngine(t *testing.T) {
	root := written(t, map[string]string{
		".commandments/custom/LoopGuardPhp.json":    neutral("loop-guard-php", "backend"),
		".commandments/custom/LoopGuardPython.json": neutral("loop-guard-python", "python"),
		".commandments/custom/NoDump.json":          `{"engine": "backend", "sin": {"name": "no-dump", "description": "x", "skill": "php/absence"}, "find": {"select": "call", "where": [{"name": "dump"}]}}`,
	})

	said, code := run(t, root, "migrate")
	if code != 0 {
		t.Errorf("migrate ended with %d: %s", code, said)
	}

	if !strings.Contains(said, "LoopGuardPhp.json now judges backend and python") {
		t.Errorf("the merge is not said: %s", said)
	}

	kept := filepath.Join(root, ".commandments/custom/LoopGuardPhp.json")
	text, err := os.ReadFile(kept)
	if err != nil {
		t.Fatal(err)
	}
	if !strings.Contains(string(text), `"backend"`) || !strings.Contains(string(text), `"python"`) {
		t.Errorf("the kept rule names %s", text)
	}

	if _, err := os.Stat(filepath.Join(root, ".commandments/custom/LoopGuardPython.json")); err == nil {
		t.Error("the copy is still there, so the project now has the rule twice")
	}

	if _, err := os.Stat(filepath.Join(root, ".commandments/custom/NoDump.json")); err != nil {
		t.Error("a rule of its own was taken away")
	}
}

func TestMigrateChangesNothingOnADryRun(t *testing.T) {
	root := written(t, map[string]string{
		".commandments/custom/LoopGuardPhp.json":    neutral("loop-guard-php", "backend"),
		".commandments/custom/LoopGuardPython.json": neutral("loop-guard-python", "python"),
	})

	said, _ := run(t, root, "migrate", "--dry-run")
	if !strings.Contains(said, "would merge") {
		t.Errorf("a dry run does not say what it would do: %s", said)
	}

	for _, name := range []string{"LoopGuardPhp.json", "LoopGuardPython.json"} {
		if _, err := os.Stat(filepath.Join(root, ".commandments/custom", name)); err != nil {
			t.Errorf("a dry run removed %s", name)
		}
	}
}

func TestMigrateNamesEveryRuleItCannotSettleAndStopsAtNone(t *testing.T) {
	root := written(t, map[string]string{
		".commandments/custom/FirstBroken.json":     `{"engine": "backend", "sin": {"name": "first", "description": "x", "skill": "php/absence"}, "find": {"select": "kind:Expr_StaticCalls"}}`,
		".commandments/custom/SecondBroken.json":    `{"engine": "python", "sin": {"name": "second", "description": "x", "skill": "php/absence"}, "find": {"select": "kind:NoSuchNode"}}`,
		".commandments/custom/LoopGuardPhp.json":    neutral("loop-guard-php", "backend"),
		".commandments/custom/LoopGuardPython.json": neutral("loop-guard-python", "python"),
	})

	said, code := run(t, root, "migrate")
	if code == 0 {
		t.Errorf("migrate passed with rules it could not read: %s", said)
	}

	for _, wanted := range []string{"Expr_StaticCalls", "NoSuchNode", "LoopGuardPhp.json now judges"} {
		if !strings.Contains(said, wanted) {
			t.Errorf("what it said does not name %q: %s", wanted, said)
		}
	}
}

func TestMigrateSaysSoWhenAProjectHasNoRulesOfItsOwn(t *testing.T) {
	said, code := run(t, t.TempDir(), "migrate")
	if code != 0 || !strings.Contains(said, "nothing to migrate") {
		t.Errorf("a project with no rules of its own: %d, %s", code, said)
	}
}

func TestMigrateCarriesTheConfigOverWithTheRule(t *testing.T) {
	root := written(t, map[string]string{
		".commandments/custom/PoeticName.json":   neutral("poetic-name", "backend"),
		".commandments/custom/GoPoeticName.json": neutral("go-poetic-name", "go"),
		".commandments/config.json": `{
  "paths": ["src"],
  "detectors": ["PoeticName", "GoPoeticName"],
  "configure": {"GoPoeticName": [{"judges": ["tests"]}]}
}
`,
	})

	said, code := run(t, root, "migrate")
	if code != 0 {
		t.Errorf("migrate ended with %d: %s", code, said)
	}

	if !strings.Contains(said, "PoeticName.json now judges backend and go") {
		t.Errorf("the wrong name survived, or the merge is not said: %s", said)
	}

	text, err := os.ReadFile(filepath.Join(root, ".commandments/config.json"))
	if err != nil {
		t.Fatal(err)
	}

	var read struct {
		Detectors []string                      `json:"detectors"`
		Configure map[string][]map[string][]any `json:"configure"`
	}
	if err := json.Unmarshal(text, &read); err != nil {
		t.Fatal(err)
	}

	if len(read.Detectors) != 1 || read.Detectors[0] != "PoeticName" {
		t.Errorf("the config names %v, so judge warns about a detector nothing declares", read.Detectors)
	}

	if _, stranded := read.Configure["GoPoeticName"]; stranded {
		t.Error("the settings are still under the replaced name, where nothing reads them")
	}

	moved, carried := read.Configure["PoeticName"]
	if !carried || len(moved) != 1 || len(moved[0]["judges"]) != 1 {
		t.Errorf("the judges setting did not come across: %v", read.Configure)
	}
}

func TestMigrateMovesNoSettingsOverSettingsThatAreAlreadyThere(t *testing.T) {
	root := written(t, map[string]string{
		".commandments/custom/PoeticName.json":   neutral("poetic-name", "backend"),
		".commandments/custom/GoPoeticName.json": neutral("go-poetic-name", "go"),
		".commandments/config.json": `{
  "paths": ["src"],
  "configure": {
    "PoeticName": [{"judges": ["src"]}],
    "GoPoeticName": [{"judges": ["tests"]}]
  }
}
`,
	})

	said, _ := run(t, root, "migrate")
	if !strings.Contains(said, "both carry settings of their own") {
		t.Errorf("the clash is not said: %s", said)
	}

	text, err := os.ReadFile(filepath.Join(root, ".commandments/config.json"))
	if err != nil {
		t.Fatal(err)
	}

	for _, name := range []string{"PoeticName", "GoPoeticName"} {
		if !strings.Contains(string(text), `"`+name+`"`) {
			t.Errorf("%s's settings were taken away although the clash was unresolved: %s", name, text)
		}
	}
}

func TestProveSaysWhereAMarkGoesWhenBothHalvesAppear(t *testing.T) {
	marked := `{"engine": "python", "sin": {"name": "no-dump", "description": "x", "skill": "php/absence"},
		"find": {"select": "call", "where": [{"name": "dump"}]}}`

	root := written(t, map[string]string{
		".commandments/custom/NoDump.json": marked,
		".commandments/custom/samples/shop/till.py": `# @sin NoDump
def show(value):
    dump(value)
`,
	})

	said, code := run(t, root, "prove", ".commandments/custom/samples/shop")
	if code == 0 {
		t.Fatalf("the sample passed, so there is nothing to explain: %s", said)
	}

	if !strings.Contains(said, "marks the line BELOW it") {
		t.Errorf("prove does not say where the mark goes: %s", said)
	}
}

func TestProveSaysNothingAboutMarksWhenOnlyOneHalfAppears(t *testing.T) {
	root := written(t, map[string]string{
		".commandments/custom/NoDump.json": `{"engine": "python", "sin": {"name": "no-dump", "description": "x", "skill": "php/absence"},
			"find": {"select": "call", "where": [{"name": "nothing_calls_this"}]}}`,
		".commandments/custom/samples/shop/till.py": `def show(value):
    # @sin NoDump
    dump(value)
`,
	})

	said, _ := run(t, root, "prove", ".commandments/custom/samples/shop")
	if strings.Contains(said, "marks the line BELOW it") {
		t.Errorf("prove explains a mark where nothing was flagged: %s", said)
	}
}
