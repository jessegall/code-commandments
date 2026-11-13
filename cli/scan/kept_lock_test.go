package scan

import (
	"os"
	"path/filepath"
	"strings"
	"sync"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/contract"
)

// A run that only reads the kept program takes no lock: one joining a fresh program may hold it the whole time.
func TestReadingTheKeptProgramTakesNoLock(t *testing.T) {
	kept := &keptLines{folder: t.TempDir(), identity: "a bridge"}
	release := atomic.Hold(kept.programPath() + ".lock")
	defer release()

	read := make(chan struct{})
	go func() {
		kept.program(nil)
		close(read)
	}()
	select {
	case <-read:
	case <-time.After(5 * time.Second):
		t.Fatal("reading the kept program waited on the lock a writer holds")
	}
}

// Two runs settling the same line add its reads to the index once.
func TestAVariantIsAddedOnceWhenRunsSettleTheSameLine(t *testing.T) {
	folder := t.TempDir()
	file := filepath.Join(folder, "cart.py")
	if err := os.WriteFile(file, []byte("x = 1\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	kept := &keptLines{folder: folder, identity: "a bridge"}
	var settling sync.WaitGroup
	for range 8 {
		settling.Add(1)
		go func() {
			defer settling.Done()
			kept.addVariant(file, []string{"prices.py"})
		}()
	}
	settling.Wait()

	index, err := os.ReadFile(kept.indexOf(file))
	if err != nil {
		t.Fatal(err)
	}
	if lines := strings.Count(string(index), "\n"); lines != 1 {
		t.Errorf("eight runs settling one line wrote %d index lines:\n%s", lines, index)
	}
}

// A stream served from the cache is named for the files it holds, whichever request first kept the header.
func TestAServedStreamIsNamedForTheFilesItHolds(t *testing.T) {
	header := contract.Header{Language: contract.TypeScript}
	files := []*contract.File{{Language: contract.TypeScript}, {Language: contract.Vue}}
	if named := languageOf(header, files); named != contract.Vue {
		t.Errorf("a stream of TypeScript and Vue files under a TypeScript header is named %s, not vue", named)
	}
	if named := languageOf(contract.Header{Language: contract.Vue}, files[:1]); named != contract.TypeScript {
		t.Errorf("a stream of TypeScript files alone under a Vue header is named %s, not typescript", named)
	}
}
