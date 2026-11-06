package info_test

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/info"
)

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
		".commandments/custom/naming_SleepyNameDetector.json": `{
			"engine": "python",
			"sin": {"name": "sleepy-name", "description": "x", "rule": "x", "skill": "python/absence"},
			"find": {"select": "identifier", "where": [{"nameMatches": "^zzz"}]}
		}`,
		".commandments/config.json": `{"paths": ["src"], "detectors": ["naming_PoeticVariableNameDetector"]}`,
	})

	chdir(t, root)

	for _, query := range []string{
		"naming_PoeticVariableNameDetector",
		"poetic-variable-name",
		"PoeticVariableName",
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
