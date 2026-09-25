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

func TestAReportAgainstTheProjectsOwnRuleIsRefused(t *testing.T) {
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

	os.WriteFile(rule, []byte(`{"engine": "backend", "sin": {"name": "raw-sql", "description": "x", "skill": "backend/absence"}, "find": {"select": "call"}}`), 0o644)

	previous, _ := os.Getwd()
	t.Cleanup(func() { os.Chdir(previous) })
	os.Chdir(root)

	var out, errs bytes.Buffer

	code, err := Command{}.Run(cli.InputOf("report", "--detector=RawSql", "--reason=wrong", "--ref=src/A.php:1"), cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatal(err)
	}

	if said := errs.String(); code != 2 || !strings.Contains(said, "RawSql is THIS project's own rule") || !strings.Contains(said, ".commandments/custom/RawSqlDetector.json") {
		t.Errorf("exit %d\n%s%s", code, out.String(), said)
	}
}
