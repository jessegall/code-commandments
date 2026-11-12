// Package bridgetest holds what a test needs of the bridges: a command for each, and the skips when the machine has no way to run one.
package bridgetest

import (
	"bytes"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/bridge"
)

func Mypy(t testing.TB) []string {
	t.Helper()
	t.Setenv("COMMANDMENTS_MYPY_PYTHON", MypyPython(t))
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	command, err := bridge.Mypy()
	if err != nil {
		t.Fatal(err)
	}

	return command
}

func MypyPython(t testing.TB) string {
	t.Helper()
	if python := os.Getenv("COMMANDMENTS_MYPY_PYTHON"); python != "" {
		return python
	}
	home, _ := os.UserHomeDir()
	built, _ := filepath.Glob(filepath.Join(home, ".cache", "code-commandments", "mypy-tree", "*", "venv", "bin", "python"))
	for _, candidate := range built {
		if exec.Command(candidate, "-c", "import mypy").Run() == nil {
			return candidate
		}
	}
	t.Skip("no Python with mypy: set COMMANDMENTS_MYPY_PYTHON")

	return ""
}

func Roslyn(t testing.TB, roots ...string) []string {
	t.Helper()
	if exec.Command("docker", "info").Run() != nil {
		t.Skip("no Docker to run the C# bridge in")
	}
	command, err := bridge.RoslynInDocker(roots)
	if err != nil {
		t.Fatal(err)
	}

	return command
}

// EndsWithItsRun starts command as a serving bridge under a shell whose pipe keeps its input open, kills that shell outright, and fails unless the bridge ends within seconds: a bridge must leave with the run that started it.
func EndsWithItsRun(t testing.TB, command []string) {
	t.Helper()

	pidFile := filepath.Join(t.TempDir(), "bridge.pid")
	quoted := make([]string, len(command))
	for at, word := range command {
		quoted[at] = "'" + word + "'"
	}

	run := exec.Command("sh", "-c", "sleep 600 | "+strings.Join(quoted, " ")+" --serve & echo $! > "+pidFile+"; wait")
	if err := run.Start(); err != nil {
		t.Fatal(err)
	}

	bridge := 0
	for deadline := time.Now().Add(10 * time.Second); bridge == 0 && time.Now().Before(deadline); time.Sleep(100 * time.Millisecond) {
		if written, err := os.ReadFile(pidFile); err == nil {
			bridge, _ = strconv.Atoi(strings.TrimSpace(string(written)))
		}
	}
	if bridge == 0 {
		t.Fatal("the bridge never started")
	}
	t.Cleanup(func() { syscall.Kill(bridge, syscall.SIGKILL) })

	time.Sleep(2 * time.Second)
	run.Process.Signal(syscall.SIGKILL)
	run.Wait()

	for deadline := time.Now().Add(10 * time.Second); time.Now().Before(deadline); time.Sleep(200 * time.Millisecond) {
		if !running(bridge) {
			return
		}
	}

	t.Error("the served bridge kept running after the run that started it was killed, its input still open")
}

func running(pid int) bool {
	if syscall.Kill(pid, 0) != nil {
		return false
	}

	stat, err := os.ReadFile(filepath.Join("/proc", strconv.Itoa(pid), "stat"))
	if err != nil {
		return true
	}

	fields := strings.Fields(string(stat[bytes.LastIndexByte(stat, ')')+1:]))

	return len(fields) == 0 || fields[0] != "Z"
}
