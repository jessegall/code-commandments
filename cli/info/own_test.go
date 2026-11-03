package info_test

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/info"
)

// TestAProjectsOwnRuleIsExplained holds `info` to what its own help promises: "a rule this project wrote into
// .commandments/custom/ is explained beside the shipped ones". It was not. The lookup read the shipped registry
// alone, so the rules a reader is LEAST likely to recognise — which the help says in those words — were the only
// ones it could not explain, while a finding named one in every report.
func TestAProjectsOwnRuleIsExplained(t *testing.T) {
	root := project(t, map[string]string{
		".commandments/custom/naming_PoeticVariableNameDetector.json": `{
			"engine": "python",
			"sin": {
				"name": "poetic-variable-name",
				"description": "A variable named for what it feels like rather than what it holds.",
				"rule": "Name it after the thing it holds.",
				"skill": "python/absence"
			},
			"find": {"select": "identifier", "where": [{"nameMatches": "^(dream|whisper)"}]}
		}`,
		// A rule the config does not turn on: a reader deciding whether to turn it on wants it explained first.
		".commandments/custom/naming_SleepyNameDetector.json": `{
			"engine": "python",
			"sin": {"name": "sleepy-name", "description": "x", "rule": "x", "skill": "python/absence"},
			"find": {"select": "identifier", "where": [{"nameMatches": "^zzz"}]}
		}`,
		".commandments/config.json": `{"paths": ["src"], "detectors": ["naming_PoeticVariableNameDetector"]}`,
	})

	chdir(t, root)

	for _, query := range []string{
		// The name a finding prints, which is the one a reader copies.
		"naming_PoeticVariableNameDetector",
		// The sin's own name, and the lenient spellings of it.
		"poetic-variable-name",
		"PoeticVariableName",
		// And the rule the config leaves off.
		"naming_SleepyNameDetector",
	} {
		found := info.Detectors(query)
		if len(found) == 0 {
			t.Errorf("%q explains nothing, and judge prints that name in every finding it makes", query)

			continue
		}

		if !strings.Contains(catalog.Name(found[0]), "naming_") && found[0].Sin().Definition().Name == "" {
			t.Errorf("%q found %q, which is no rule of the project's own", query, catalog.Name(found[0]))
		}
	}
}

// TestAShippedRuleIsStillExplainedFromAProjectWithOwnRules holds the shipped ones to answering as they did, since
// a project's own rules are searched after them and must not shadow them.
func TestAShippedRuleIsStillExplainedFromAProjectWithOwnRules(t *testing.T) {
	chdir(t, project(t, map[string]string{
		".commandments/custom/Whatever.json": `{
			"engine": "python",
			"sin": {"name": "whatever", "description": "x", "rule": "x", "skill": "python/absence"},
			"find": {"select": "identifier"}
		}`,
	}))

	if len(info.Detectors("deep-python-nesting")) == 0 {
		t.Error("a shipped rule is no longer explained from a project that has rules of its own")
	}
}

// project is a project holding the files, by their path under its root.
func project(t *testing.T, files map[string]string) string {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

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

// chdir puts the test in the folder and puts the working folder back when it ends, which the go this module
// declares has no t.Chdir for.
func chdir(t *testing.T, folder string) {
	t.Helper()

	previous, err := os.Getwd()
	if err != nil {
		t.Fatal(err)
	}

	if err := os.Chdir(folder); err != nil {
		t.Fatal(err)
	}

	t.Cleanup(func() { os.Chdir(previous) })
}
