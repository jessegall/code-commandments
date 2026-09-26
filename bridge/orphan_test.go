package bridge

import (
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"testing"
	"time"
)

// TestABridgeContainerStopsWhenTheRunThatStartedItDies starts the C# bridge serving, under a process that is then
// killed outright, and holds its container to stopping: a container a dead run leaves behind compiles for no one.
func TestABridgeContainerStopsWhenTheRunThatStartedItDies(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	command := TestRoslyn(t, root)
	caller := exec.Command("sh", append([]string{"-c", `exec 3<&0; "$@" <&3 & wait`, "sh"}, append(command, "--serve")...)...)
	// The bridge's input stays open after its caller dies, as it does while a bridge busy compiling reads none of it:
	// only stopping the container ends the run.
	reading, input, err := os.Pipe()
	if err != nil {
		t.Fatal(err)
	}
	defer input.Close()
	caller.Stdin = reading
	if err := caller.Start(); err != nil {
		t.Fatal(err)
	}
	reading.Close()
	launcher := ""
	if !within(10*time.Second, func() bool {
		launcher = childOf(caller.Process.Pid)

		return launcher != ""
	}) {
		t.Skip("no /proc to find the launcher's process in")
	}
	name := "code-commandments-roslyn-run-" + launcher + "-"
	running := func() bool {
		out, _ := exec.Command("docker", "ps", "--format", "{{.Names}}", "--filter", "name="+name).Output()

		return strings.TrimSpace(string(out)) != ""
	}
	if !within(60*time.Second, running) {
		t.Fatal("the bridge's container never started")
	}

	caller.Process.Kill()
	caller.Wait()

	if !within(30*time.Second, func() bool { return !running() }) {
		t.Error("the bridge's container outlived the run that started it")
	}
}

// within says whether the condition holds before the time is up, asking twice a second.
func within(limit time.Duration, holds func() bool) bool {
	for deadline := time.Now().Add(limit); time.Now().Before(deadline); time.Sleep(500 * time.Millisecond) {
		if holds() {
			return true
		}
	}

	return false
}

// childOf is the process id of a process the parent started, read from /proc; empty when there is none to read.
func childOf(parent int) string {
	stats, _ := filepath.Glob("/proc/[0-9]*/stat")
	for _, stat := range stats {
		raw, err := os.ReadFile(stat)
		if err != nil {
			continue
		}
		_, after, found := strings.Cut(string(raw), ") ")
		if fields := strings.Fields(after); found && len(fields) > 1 && fields[1] == strconv.Itoa(parent) {
			return filepath.Base(filepath.Dir(stat))
		}
	}

	return ""
}
