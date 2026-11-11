package judge

import (
	"io"
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
)

func TestACSharpFileNoBridgeCanReadIsNamedAsSkippedInTheAnswer(t *testing.T) {
	t.Setenv("COMMANDMENTS_ROSLYN", "")
	folder := t.TempDir()
	if err := os.WriteFile(filepath.Join(folder, "Order.cs"), []byte("class Order {}\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	judgement, files, err := Command{}.judgeUnits(scan.Walk([]string{folder}, source.Excluded{}), nil, options{}, nil, cli.Console{Out: io.Discard, Err: io.Discard})

	if err != nil {
		t.Fatal(err)
	}
	if files != 0 {
		t.Fatalf("%d C# files were read with no bridge to read them", files)
	}
	if !slices.Contains(judgement.Skipped, "the C# bridge") {
		t.Fatalf("a C# file no bridge could read left the answer silent: skipped %v", judgement.Skipped)
	}
}

func TestABridgeThatReadsNoneOfItsCSharpFilesIsNamedAsSkippedAndNotCalledClean(t *testing.T) {
	folder := t.TempDir()
	if err := os.WriteFile(filepath.Join(folder, "Order.cs"), []byte("class Order {}\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	script := filepath.Join(t.TempDir(), "roslyn-bridge")
	answer := `while read request; do echo '{"header":{"contract":"tree","version":1,"language":"csharp","bridge":{"name":"t","version":"1"},"roots":["/p"]}}'; echo '{"trailer":{"files":0}}'; done`
	if err := os.WriteFile(script, []byte("#!/bin/sh\n"+answer+"\n"), 0o755); err != nil {
		t.Fatal(err)
	}
	t.Setenv("COMMANDMENTS_ROSLYN", script)

	judgement, files, err := Command{}.judgeUnits(scan.Walk([]string{folder}, source.Excluded{}), nil, options{}, cli.NewProgress(io.Discard), cli.Console{Out: io.Discard, Err: io.Discard})

	if err != nil {
		t.Fatal(err)
	}
	if files != 0 || !slices.Contains(judgement.Skipped, "the C# bridge") {
		t.Fatalf("a bridge that read none of its files answered: %d files, skipped %v", files, judgement.Skipped)
	}
}

func TestAFolderWithNoCSharpSaysNothingOfTheCSharpBridge(t *testing.T) {
	judgement, _, err := Command{}.judgeUnits(scan.Walk([]string{t.TempDir()}, source.Excluded{}), nil, options{}, nil, cli.Console{Out: io.Discard, Err: io.Discard})

	if err != nil {
		t.Fatal(err)
	}
	if len(judgement.Skipped) != 0 {
		t.Fatalf("a folder holding no C# named a skipped bridge: %v", judgement.Skipped)
	}
}
