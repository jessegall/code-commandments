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

const limit = 3 << 30

const started = "the hog is touching memory past the cap"

const killed = 137

func TestARunPastTheCapIsKilledByTheMachine(t *testing.T) {
	if os.Getenv("COMMANDMENTS_DEV_HOG") != "" {
		hog()

		return
	}
	if err := exec.Command("docker", "info").Run(); err != nil {
		t.Skip("docker is not reachable: scripts/dev cannot start a container")
	}
	dev := filepath.Join("..", "..", "scripts", "dev")
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

func hog() {
	fmt.Println(started)
	held := make([]byte, limit+limit/3)
	for i := 0; i < len(held); i += 4096 {
		held[i] = 1
	}
	os.Exit(0)
}
