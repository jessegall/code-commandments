package judge

import (
	"bytes"
	"io"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli"
)

// TestAJudgeWhoseCSharpBridgeFailsJudgesEverythingElse holds judge to finishing when the C# bridge dies or hangs:
// the run says why C# is left unread, and the PHP beside it is judged.
func TestAJudgeWhoseCSharpBridgeFailsJudgesEverythingElse(t *testing.T) {
	for name, bridge := range map[string]string{
		"dies":  `echo 'roslyn-bridge: there is no file or folder at /gone to read' >&2; exit 1`,
		"hangs": `read request; sleep 60`,
	} {
		t.Run(name, func(t *testing.T) {
			root := ownRuleProject(t)
			if err := os.WriteFile(filepath.Join(root, "src", "Invoice.cs"), []byte("namespace App;\n\npublic sealed class Invoice {}\n"), 0o644); err != nil {
				t.Fatal(err)
			}
			script := filepath.Join(t.TempDir(), "roslyn-bridge")
			if err := os.WriteFile(script, []byte("#!/bin/sh\n"+bridge+"\n"), 0o755); err != nil {
				t.Fatal(err)
			}
			t.Setenv("COMMANDMENTS_ROSLYN", script)
			t.Setenv("COMMANDMENTS_BRIDGE_QUIET", "2s")
			previous, _ := os.Getwd()
			t.Cleanup(func() { os.Chdir(previous) })
			os.Chdir(root)

			var out, errs bytes.Buffer
			start := time.Now()
			warned := stderrOf(t, func() {
				if _, err := (Command{}).Run(cli.InputOf("judge", "src", "--no-checklist", "--parallel=1"), cli.Console{Out: &out, Err: &errs}); err != nil {
					t.Error(err)
				}
			})
			if took := time.Since(start); took > 30*time.Second {
				t.Errorf("judge took %s", took)
			}
			if !strings.Contains(warned, "left unread — the C# bridge failed: ") {
				t.Errorf("judge did not say the C# bridge failed:\n%s", warned)
			}
			if !strings.Contains(out.String(), "[RawSqlDetector (custom)]") {
				t.Errorf("the PHP beside the C# was not judged:\n%s\n%s", out.String(), errs.String())
			}
		})
	}
}

// stderrOf is what the call wrote to the process's stderr.
func stderrOf(t *testing.T, call func()) string {
	t.Helper()
	reader, writer, err := os.Pipe()
	if err != nil {
		t.Fatal(err)
	}
	previous := os.Stderr
	os.Stderr = writer
	said := make(chan string)
	go func() {
		written, _ := io.ReadAll(reader)
		said <- string(written)
	}()
	call()
	os.Stderr = previous
	writer.Close()

	return <-said
}
