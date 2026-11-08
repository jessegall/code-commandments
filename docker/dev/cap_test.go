// Package dev proves the dev container's cap is enforced by the machine, not merely targeted by GOMEMLIMIT.
package dev

import (
	"errors"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"
	"testing"
	"time"
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

func TestAStoppedRunLeavesNoContainerWorking(t *testing.T) {
	if err := exec.Command("docker", "info").Run(); err != nil {
		t.Skip("docker is not reachable: scripts/dev cannot start a container")
	}

	marker := strconv.Itoa(100000 + os.Getpid())
	client := exec.Command(filepath.Join("..", "..", "scripts", "dev"), "sleep", marker)
	if err := client.Start(); err != nil {
		t.Fatal(err)
	}

	if !eventually(60*time.Second, func() bool { return containerRuns(marker) }) {
		_ = client.Process.Kill()
		t.Fatal("the run's container never started")
	}

	if err := client.Process.Signal(syscall.SIGTERM); err != nil {
		t.Fatal(err)
	}

	_ = client.Wait()

	if !eventually(30*time.Second, func() bool { return !containerRuns(marker) }) {
		t.Fatalf("the container of a stopped run is still working on sleep %s", marker)
	}
}

func containerRuns(marker string) bool {
	out, err := exec.Command("docker", "ps", "--no-trunc", "--filter", "label=code-commandments.dev=run", "--format", "{{.Command}}").Output()

	return err == nil && strings.Contains(string(out), marker)
}

func eventually(within time.Duration, holds func() bool) bool {
	for until := time.Now().Add(within); time.Now().Before(until); time.Sleep(500 * time.Millisecond) {
		if holds() {
			return true
		}
	}

	return false
}

func hog() {
	fmt.Println(started)
	held := make([]byte, limit+limit/3)
	for i := 0; i < len(held); i += 4096 {
		held[i] = 1
	}
	os.Exit(0)
}
