// Package dev proves the dev container's cap is enforced by the machine, not merely targeted by GOMEMLIMIT.
package dev

import (
	"errors"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

// limit is the dev container's memory cap: scripts/dev starts it with --memory=3g and no swap.
const limit = 3 << 30

// started is what the hog says before it touches a page, so a container that died of anything else cannot pass.
const started = "the hog is touching memory past the cap"

// killed is the exit code docker run hands back when the container's init sees its child die of SIGKILL: 128 + 9.
const killed = 137

// TestARunPastTheCapIsKilledByTheMachine has scripts/dev start a container of its own, with the very flags every run
// gets, and a child in it that touches more memory than the cap allows; it sees the kernel kill that child, GOMEMLIMIT
// set or not. The child runs apart from the suite, so the kill lands in its own container and never on a neighbouring
// package's test. Without docker it skips.
func TestARunPastTheCapIsKilledByTheMachine(t *testing.T) {
	if os.Getenv("COMMANDMENTS_DEV_HOG") != "" {
		hog()

		return
	}
	if err := exec.Command("docker", "info").Run(); err != nil {
		t.Skip("docker is not reachable: scripts/dev cannot start a container")
	}
	dev := filepath.Join("..", "..", "scripts", "dev")
	// Read so Go's test cache watches the script: a change to its cap runs the hog again rather than replaying a pass.
	if _, err := os.ReadFile(dev); err != nil {
		t.Fatalf("scripts/dev: %v", err)
	}
	script := "go test -c -o /tmp/dev.test . && COMMANDMENTS_DEV_HOG=1 exec /tmp/dev.test -test.run='^TestARunPastTheCapIsKilledByTheMachine$'"
	out, err := exec.Command(dev, "sh", "-c", script).CombinedOutput()
	if !strings.Contains(string(out), started) {
		t.Fatalf("the hog never started: %v\n%s", err, out)
	}
	var exit *exec.ExitError
	if !errors.As(err, &exit) {
		t.Fatalf("a run past the cap was not stopped: %v\n%s", err, out)
	}
	if exit.ExitCode() != killed {
		t.Fatalf("a run past the cap ended with %v, not the kernel's SIGKILL\n%s", err, out)
	}
}

// hog touches a page at a time past the cap, so the memory is resident rather than only reserved.
func hog() {
	fmt.Println(started)
	held := make([]byte, limit+limit/3)
	for i := 0; i < len(held); i += 4096 {
		held[i] = 1
	}
	os.Exit(0)
}
