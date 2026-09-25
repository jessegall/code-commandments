// Package dev proves the dev container's cap is enforced by the machine, not merely targeted by GOMEMLIMIT.
package dev

import (
	"errors"
	"os"
	"os/exec"
	"strconv"
	"strings"
	"syscall"
	"testing"
)

// limit is the dev container's memory cap: scripts/dev starts it with --memory=3g and no swap.
const limit = 3 << 30

// TestARunPastTheCapIsKilledByTheMachine starts a child that touches more memory than the cap allows and sees
// the kernel kill it, GOMEMLIMIT set or not. Outside a container capped at the limit it skips: there it would
// only take the memory.
func TestARunPastTheCapIsKilledByTheMachine(t *testing.T) {
	if os.Getenv("COMMANDMENTS_DEV_HOG") != "" {
		hog()

		return
	}
	capped, err := os.ReadFile("/sys/fs/cgroup/memory.max")
	if err != nil {
		t.Skip("no cgroup memory limit: run it through scripts/dev")
	}
	bytes, err := strconv.Atoi(strings.TrimSpace(string(capped)))
	if err != nil || bytes > limit {
		t.Skipf("the memory limit is %s, not the dev container's 3 GB: run it through scripts/dev", strings.TrimSpace(string(capped)))
	}
	child := exec.Command(os.Args[0], "-test.run=^TestARunPastTheCapIsKilledByTheMachine$")
	child.Env = append(os.Environ(), "COMMANDMENTS_DEV_HOG=1")
	err = child.Run()
	var exit *exec.ExitError
	if !errors.As(err, &exit) {
		t.Fatalf("a run past the cap was not stopped: %v", err)
	}
	status := exit.Sys().(syscall.WaitStatus)
	if !status.Signaled() || status.Signal() != syscall.SIGKILL {
		t.Fatalf("a run past the cap ended with %v, not the kernel's SIGKILL", err)
	}
}

// hog touches a page at a time past the cap, so the memory is resident rather than only reserved.
func hog() {
	held := make([]byte, limit+limit/3)
	for i := 0; i < len(held); i += 4096 {
		held[i] = 1
	}
	os.Exit(0)
}
