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

const windowsFolder = "windows"

// EditWindow is one agent's live edits, gathered on disk while its window is open: the plugin's script (.journal-plugin/hook.py) opens it with the first edit and queues the one run that judges them, every later edit only joins it, and that run closes it here when it takes them. What an agent left behind is swept once nothing has touched it for the hours the project keeps an unused cache.
type EditWindow struct {
	folder string
	agent  int
}

func EditWindowIn(data string, agent int) EditWindow {
	folder := filepath.Join(data, windowsFolder)
	bundle.Sweep(folder, time.Now())

	return EditWindow{folder: folder, agent: agent}
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

func (w EditWindow) path(suffix string) string {
	return filepath.Join(w.folder, strconv.Itoa(w.agent)+suffix)
}
