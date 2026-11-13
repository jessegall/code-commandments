//go:build !windows

package atomic

import (
	"bufio"
	"os/exec"
	"path/filepath"
	"testing"
	"time"
)

// A turn is a kernel lock on an open file, so a holder that dies however it dies, kill -9 included, lets the next
// run in at once: the file left on disk holds nobody up.
func TestATurnWhoseHolderWasKilledIsTakenAtOnce(t *testing.T) {
	path := filepath.Join(t.TempDir(), "reading.lock")
	holder := exec.Command("python3", "-c",
		"import fcntl,sys,time; f=open(sys.argv[1],'a'); fcntl.flock(f,fcntl.LOCK_EX); print('held',flush=True); time.sleep(60)", path)
	out, err := holder.StdoutPipe()
	if err != nil {
		t.Fatal(err)
	}
	if err := holder.Start(); err != nil {
		t.Skip("no python3 here to hold the lock from another process")
	}
	if line, _ := bufio.NewReader(out).ReadString('\n'); line != "held\n" {
		t.Fatalf("the holder said %q instead of holding the lock", line)
	}

	waited := false
	if err := holder.Process.Kill(); err != nil {
		t.Fatal(err)
	}
	_ = holder.Wait()

	began := time.Now()
	release := HoldInTurn(path, func() { waited = true }, 5*time.Second)
	defer release()

	if waited || time.Since(began) > time.Second {
		t.Errorf("the next run waited %s on a lock whose holder was killed", time.Since(began))
	}
}
