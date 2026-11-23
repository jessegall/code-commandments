package scan

import (
	"encoding/hex"
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/walk"
)

// keptPrints is the file a project's runs keep each file's fingerprint in, beside the size and change time the file had
// then, so a later run reads a file for its fingerprint only when either moved. A file changed less than settledAfter
// before a run ends is left out, since a second edit inside one tick of the clock would not move its time.
type keptPrints struct {
	path string
}

const settledAfter = 2 * time.Second

var printsHeld sync.Map

var printsKeptAt struct {
	sync.Mutex
	read map[string]int64
}

func printsKeptFor(roots []string) keptPrints {
	if len(roots) == 0 {
		return keptPrints{}
	}
	shared := workspace.SharedFolder(roots[0])
	if shared == "" {
		return keptPrints{}
	}

	return keptPrints{filepath.Join(shared, "prints.json")}
}

func (k keptPrints) read() map[string][2]string {
	kept := map[string][2]string{}
	if raw, err := os.ReadFile(k.path); err == nil {
		_ = json.Unmarshal(raw, &kept)
	}

	return kept
}

func (k keptPrints) hold() {
	if k.path == "" {
		return
	}
	if _, held := printsHeld.LoadOrStore(k.path, true); held {
		return
	}
	for path, kept := range k.read() {
		print, err := hex.DecodeString(kept[1])
		if err != nil || len(print) != 32 {
			continue
		}
		walk.HoldPrint(path, kept[0], [32]byte(print))
	}
	k.keptAt(walk.ReadForPrint())
}

func (k keptPrints) keptAt(read int64) (was int64) {
	printsKeptAt.Lock()
	defer printsKeptAt.Unlock()
	if printsKeptAt.read == nil {
		printsKeptAt.read = map[string]int64{}
	}
	was = printsKeptAt.read[k.path]
	printsKeptAt.read[k.path] = read

	return was
}

func (k keptPrints) keep() {
	if k.path == "" {
		return
	}
	read := walk.ReadForPrint()
	was := k.keptAt(read)
	if cli.Timed() {
		fmt.Fprintf(os.Stderr, "· fingerprints: %d files read for theirs since the last time they were kept\n", read-was)
	}
	if read == was {
		return
	}

	defer atomic.Hold(k.path + ".lock")()
	kept, now := k.read(), time.Now()
	for path := range kept {
		if walk.Mark(path) == "" {
			delete(kept, path)
		}
	}
	walk.HeldPrints(func(path, mark string, print [32]byte) {
		changed, _, _ := strings.Cut(mark, "\x00")
		if at, err := time.Parse(time.RFC3339Nano, changed); err != nil || now.Sub(at) < settledAfter {
			delete(kept, path)

			return
		}
		kept[path] = [2]string{mark, hex.EncodeToString(print[:])}
	})
	if raw, err := json.Marshal(kept); err == nil {
		_ = atomic.Write(k.path, string(raw))
	}
}
