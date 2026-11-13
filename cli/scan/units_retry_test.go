package scan

import (
	"os"
	"path/filepath"
	"strconv"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func bridgeDyingOnce(t *testing.T, header string) (command string, runs func() int) {
	t.Helper()
	folder := t.TempDir()
	counter := filepath.Join(folder, "runs")
	script := filepath.Join(folder, "bridge")

	written := "#!/bin/sh\nruns=$(cat " + counter + " 2>/dev/null || echo 0)\n" +
		"echo $((runs + 1)) > " + counter + "\n" +
		"if [ \"$runs\" = \"0\" ]; then echo 'Fatal error. System.AccessViolationException' >&2; exit 134; fi\n" +
		"read line\n" +
		"printf '%s\\n' '" + header + "'\n" +
		"printf '%s\\n' '{\"trailer\": {\"files\": 0}}'\n"

	if err := os.WriteFile(script, []byte(written), 0o755); err != nil {
		t.Fatal(err)
	}

	return script, func() int {
		raw, err := os.ReadFile(counter)
		if err != nil {
			return 0
		}

		count, _ := strconv.Atoi(string(raw[:len(raw)-1]))

		return count
	}
}

func TestABridgeThatDiesIsAskedOnceMore(t *testing.T) {
	project := t.TempDir()
	source := filepath.Join(project, "Order.cs")
	if err := os.WriteFile(source, []byte("namespace Shop;\n\npublic sealed class Order;\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	header := `{"header": {"contract": "tree", "version": 1, "language": "csharp", "bridge": {"name": "test", "version": "1"}, "roots": ["` + project + `"]}}`
	command, runs := bridgeDyingOnce(t, header)
	t.Setenv("COMMANDMENTS_ROSLYN", command)

	units, err := readUnits(CSharp, []string{project}, []string{source}, func(*engine.Codebase) error { return nil })
	if err != nil {
		t.Fatalf("the bridge died once and the read gave up: %v", err)
	}

	if units != nil {
		defer units.Close()
	}

	if asked := runs(); asked != 2 {
		t.Errorf("the bridge ran %d times, want 2: one death and one ask that answers", asked)
	}
}
