package judge

import (
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"
	"testing"
)

func TestJudgingACSharpProjectCountsTheFilesTheBridgeReadAndTheAnswersItAsked(t *testing.T) {
	project, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	var records []string
	for _, name := range []string{"Order.cs", "Invoice.cs", "Shipment.cs"} {
		path := filepath.Join(project, name)
		writeInto(t, project, name, "class "+strings.TrimSuffix(name, ".cs")+" {}\n")
		records = append(records, fmt.Sprintf(`{"file":{"path":%q,"language":"csharp","errors":0,"comments":[],"root":{"id":0,"kind":"file","span":[0,1,1]}}}`, path))
	}
	header := fmt.Sprintf(`{"header":{"contract":"tree","version":5,"language":"csharp","bridge":{"name":"t","version":"1"},"roots":[%q]}}`, project)
	answer := "while read request; do echo '" + header + "'; echo '" + strings.Join(records, "'; echo '") + `'; echo '{"trailer":{"files":3}}'; done`
	script := filepath.Join(t.TempDir(), "roslyn-bridge")
	if err := os.WriteFile(script, []byte("#!/bin/sh\n"+answer+"\n"), 0o755); err != nil {
		t.Fatal(err)
	}
	t.Setenv("COMMANDMENTS_ROSLYN", script)

	_, said := judgedAt(t, project, false, ".", "--no-checklist", "--parallel=1", "--sin=redundant-else")

	line := regexp.MustCompile(`read (\d+) files .*\((\d+) rule answers from memory, (\d+) asked\)`).FindStringSubmatch(said)
	if line == nil {
		t.Fatalf("the judging said no read line:\n%s", said)
	}
	if line[1] != "3" {
		t.Errorf("judging three C# files says it read %s:\n%s", line[1], said)
	}
	remembered, _ := strconv.Atoi(line[2])
	asked, _ := strconv.Atoi(line[3])
	if remembered+asked != 3 {
		t.Errorf("one rule over three C# files gave %d answers from memory and %d asked, not 3 in all:\n%s", remembered, asked, said)
	}
}
