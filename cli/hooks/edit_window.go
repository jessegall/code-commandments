package hooks

import (
	"bytes"
	"os"
	"path/filepath"
	"strconv"
	"time"

	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/cli/atomic"
)

const (
	windowsFolder = "windows"

	windowLeftOpenAfter = 15 * time.Minute
)

// EditWindow is one agent's live edits, gathered on disk while its window is open: the first edit opens it and starts the one background run that judges them, every later edit only joins it, and that run closes it when it takes them. A window whose run died without closing it counts as closed once it has stood open far longer than any window lasts, and what an agent left behind is swept once nothing has touched it for the hours the project keeps an unused cache. A run that finds the project being read by another puts the edits it took back, closed, so the agent's next edit opens a window that takes them too: nothing is dropped.
type EditWindow struct {
	folder string
	agent  int
}

func EditWindowIn(data string, agent int) EditWindow {
	folder := filepath.Join(data, windowsFolder)
	bundle.Sweep(folder, time.Now())

	return EditWindow{folder: folder, agent: agent}
}

func (w EditWindow) Gather(edit []byte) bool {
	release := atomic.Hold(w.path(".lock"))
	defer release()

	file, err := os.OpenFile(w.path(".edits"), os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0o644)
	if err != nil {
		return false
	}
	_, err = file.Write(append(bytes.TrimSpace(edit), '\n'))
	file.Close()

	if err != nil {
		return false
	}

	if opened, err := os.Stat(w.path(".open")); err == nil && time.Since(opened.ModTime()) < windowLeftOpenAfter {
		return false
	}

	return os.WriteFile(w.path(".open"), nil, 0o644) == nil
}

func (w EditWindow) IsOpen() bool {
	_, err := os.Stat(w.path(".open"))

	return err == nil
}

func (w EditWindow) Close() [][]byte {
	release := atomic.Hold(w.path(".lock"))
	defer release()

	gathered, _ := os.ReadFile(w.path(".edits"))
	os.Remove(w.path(".edits"))
	os.Remove(w.path(".open"))

	var edits [][]byte
	for _, edit := range bytes.Split(gathered, []byte("\n")) {
		if len(bytes.TrimSpace(edit)) > 0 {
			edits = append(edits, edit)
		}
	}

	return edits
}

func (w EditWindow) Reopen() {
	release := atomic.Hold(w.path(".lock"))
	defer release()

	os.Remove(w.path(".open"))
}

func (w EditWindow) path(suffix string) string {
	return filepath.Join(w.folder, strconv.Itoa(w.agent)+suffix)
}
