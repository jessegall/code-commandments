package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"
	"time"
)

// TestADetachedRunReadsTheWholeMoment holds the hand-over the detached advice depends on: the moment is a file of its
// own, gone from the disk, that a run started and let go of still reads in full. A pipe would need this process to
// feed it, and this process answers and ends before the run reads.
func TestADetachedRunReadsTheWholeMoment(t *testing.T) {
	raw := []byte(`{"event":"hook.PostToolUse","tool":{"name":"Write","file":"src/Order.php"}}` + "\n")

	moment, err := momentFile(raw)
	if err != nil {
		t.Fatal(err)
	}

	if _, err := os.Stat(moment.Name()); !os.IsNotExist(err) {
		t.Errorf("the moment's file is still on the disk: %v", err)
	}

	read := filepath.Join(t.TempDir(), "read")
	run := exec.Command("sh", "-c", "cat > "+read)
	run.Stdin = moment
	detach(run)

	if err := run.Start(); err != nil {
		t.Fatal(err)
	}
	moment.Close()
	run.Process.Release()

	for deadline := time.Now().Add(5 * time.Second); time.Now().Before(deadline); time.Sleep(20 * time.Millisecond) {
		if got, _ := os.ReadFile(read); string(got) == string(raw) {
			return
		}
	}

	got, _ := os.ReadFile(read)
	t.Errorf("the run read %q, want %q", got, raw)
}
