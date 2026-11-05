package report

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	_ "github.com/jessegall/code-commandments/registry"
)

func TestAReportAgainstTheProjectsOwnRuleIsRefusedByEitherNameItIsKnownBy(t *testing.T) {
	guard := t.TempDir()
	if err := os.WriteFile(filepath.Join(guard, "gh"), []byte("#!/bin/sh\necho 'gh is guarded in this test' >&2\nexit 1\n"), 0o755); err != nil {
		t.Fatal(err)
	}

	t.Setenv("PATH", guard+string(os.PathListSeparator)+os.Getenv("PATH"))

	root := t.TempDir()
	rule := filepath.Join(root, ".commandments", "custom", "RawSqlDetector.json")

	if err := os.MkdirAll(filepath.Dir(rule), 0o755); err != nil {
		t.Fatal(err)
	}

	os.WriteFile(rule, []byte(`{"engine": "backend", "sin": {"name": "raw-sql", "description": "x", "skill": "php/absence"}, "find": {"select": "call"}}`), 0o644)

	previous, _ := os.Getwd()
	t.Cleanup(func() { os.Chdir(previous) })
	os.Chdir(root)

	// Either name the reporter was shown is the rule: the checklist prints the rule's own, a chat mark and
	// `info` print the SIN's. Named by its sin, the refusal used to miss and the report was filed upstream,
	// where a rule that lives in this project cannot be fixed (issue 824).
	for _, named := range []string{"RawSql", "RawSqlDetector", "raw-sql"} {
		var out, errs bytes.Buffer

		code, err := Command{}.Run(cli.InputOf("report", "--detector="+named, "--reason=wrong", "--ref=src/A.php:1"), cli.Console{Out: &out, Err: &errs})
		if err != nil {
			t.Fatal(err)
		}

		if said := errs.String(); code != 2 || !strings.Contains(said, named+" is THIS project's own rule") || !strings.Contains(said, ".commandments/custom/RawSqlDetector.json") {
			t.Errorf("--detector=%s: exit %d\n%s%s", named, code, out.String(), said)
		}
	}
}
