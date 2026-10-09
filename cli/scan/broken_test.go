package scan

import (
	"errors"
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine/php"
)

// TestABridgeThatStopsPartwayLeavesWhatItReadToBeJudged holds the load to the files a bridge wrote before it broke
// off, as a bridge killed after its first file does: they are loaded, and the load says which bridge stopped.
func TestABridgeThatStopsPartwayLeavesWhatItReadToBeJudged(t *testing.T) {
	root := t.TempDir()
	first, second := filepath.Join(root, "First.php"), filepath.Join(root, "Second.php")
	for _, file := range []string{first, second} {
		if err := os.WriteFile(file, []byte("<?php\nfinal class "+filepath.Base(file[:len(file)-4])+" {}\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	written, err := php.Here().Stream(first)
	if err != nil {
		t.Fatal(err)
	}
	whole := readers
	readers = []reader{{name: "PHP", languages: []source.Language{source.PHP}, stream: func(_, _ []string, _ func()) (*contract.Stream, error) {
		return written, errors.New("signal: killed")
	}}}
	t.Cleanup(func() { readers = whole })

	codebase, err := Walk([]string{root}, source.Excluded{}).Load()

	var incomplete Incomplete
	if !errors.As(err, &incomplete) || len(incomplete.Bridges) != 1 || incomplete.Bridges[0] != "the PHP bridge" {
		t.Fatalf("the load answers %v", err)
	}
	if codebase == nil || len(codebase.Files()) != 1 {
		t.Fatalf("the load holds %v", codebase)
	}
}
