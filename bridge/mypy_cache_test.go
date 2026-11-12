package bridge_test

import (
	"bytes"
	"compress/zlib"
	"encoding/json"
	"io"
	"io/fs"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
)

var pythonShop = map[string]string{
	"price.py":  "class Price:\n    amount: int = 0\n",
	"basket.py": "from price import Price\n\n\nclass Basket:\n    def total(self) -> Price:\n        return Price()\n",
}

func pythonRun(t *testing.T, root string) string {
	t.Helper()

	command, err := bridge.Mypy()
	if err != nil {
		t.Fatal(err)
	}

	stream, err := bridge.Once(command, root)
	if err != nil {
		t.Fatal(err)
	}

	written, err := json.Marshal(stream.Files)
	if err != nil {
		t.Fatal(err)
	}

	return string(written)
}

func tamperedLines(t *testing.T, folder, was, now string) int {
	t.Helper()

	tampered := 0
	filepath.WalkDir(folder, func(path string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() || strings.HasPrefix(entry.Name(), ".") {
			return nil
		}

		stored, err := os.ReadFile(path)
		if err != nil {
			return nil
		}

		reader, err := zlib.NewReader(bytes.NewReader(stored))
		if err != nil {
			return nil
		}

		line, err := io.ReadAll(reader)
		if err != nil || !bytes.Contains(line, []byte(was)) {
			return nil
		}

		var packed bytes.Buffer
		writer := zlib.NewWriter(&packed)
		writer.Write(bytes.ReplaceAll(line, []byte(was), []byte(now)))
		writer.Close()

		if os.WriteFile(path, packed.Bytes(), 0o644) == nil {
			tampered++
		}

		return nil
	})

	return tampered
}

func pythonLines(t *testing.T) string {
	t.Helper()

	cache := t.TempDir()
	t.Setenv("COMMANDMENTS_MYPY_PYTHON", bridgetest.MypyPython(t))
	t.Setenv("XDG_CACHE_HOME", cache)
	t.Setenv("JOURNAL_PLUGIN_DATA", t.TempDir())

	return filepath.Join(cache, "code-commandments", "trees", "python")
}

func TestASecondRunOverAnUnchangedPythonProjectCompilesNothing(t *testing.T) {
	lines := pythonLines(t)
	root := checkoutHolding(t, pythonShop)

	pythonRun(t, root)

	if tamperedLines(t, lines, `"Basket"`, `"KeptBasket"`) == 0 {
		t.Fatal("the first run kept no line for basket.py")
	}

	if again := pythonRun(t, root); !strings.Contains(again, "KeptBasket") {
		t.Error("the second run compiled basket.py again instead of answering from the cache")
	}
}

func TestAChangeToAModuleTheOthersImportInvalidatesTheirKeptLines(t *testing.T) {
	lines := pythonLines(t)
	root := checkoutHolding(t, pythonShop)

	pythonRun(t, root)
	tamperedLines(t, lines, `"Basket"`, `"KeptBasket"`)

	if err := os.WriteFile(filepath.Join(root, "price.py"), []byte("class Price:\n    amount: int = 0\n    currency: str = \"EUR\"\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if again := pythonRun(t, root); strings.Contains(again, "KeptBasket") || !strings.Contains(again, "Basket") {
		t.Error("basket.py imports price.py, which changed, yet its kept line was written")
	}
}

func TestAWorktreeHoldingTheSamePythonSharesTheKeptLines(t *testing.T) {
	lines := pythonLines(t)
	root := checkoutHolding(t, pythonShop)

	pythonRun(t, root)
	tamperedLines(t, lines, `"Basket"`, `"KeptBasket"`)

	worktree := checkoutHolding(t, pythonShop)
	again := pythonRun(t, worktree)

	if !strings.Contains(again, "KeptBasket") {
		t.Error("a worktree holding the same modules compiled them again instead of sharing the kept lines")
	}

	if strings.Contains(again, root) || !strings.Contains(again, worktree) {
		t.Error("the worktree's kept lines name the checkout they were first written in, not its own")
	}
}

func TestAServedPythonBridgeEndsOnceTheRunThatStartedItIsKilled(t *testing.T) {
	pythonLines(t)
	command, err := bridge.Mypy()
	if err != nil {
		t.Fatal(err)
	}

	pidFile := filepath.Join(t.TempDir(), "bridge.pid")
	quoted := make([]string, len(command))
	for at, word := range command {
		quoted[at] = "'" + word + "'"
	}

	run := exec.Command("sh", "-c", "sleep 600 | "+strings.Join(quoted, " ")+" --serve & echo $! > "+pidFile+"; wait")
	if err := run.Start(); err != nil {
		t.Fatal(err)
	}

	var bridgePid int
	for deadline := time.Now().Add(10 * time.Second); time.Now().Before(deadline); time.Sleep(100 * time.Millisecond) {
		if written, err := os.ReadFile(pidFile); err == nil {
			if pid, err := strconv.Atoi(strings.TrimSpace(string(written))); err == nil {
				bridgePid = pid
				break
			}
		}
	}
	if bridgePid == 0 {
		t.Fatal("the bridge never started")
	}
	t.Cleanup(func() { syscall.Kill(bridgePid, syscall.SIGKILL) })

	time.Sleep(2 * time.Second)
	run.Process.Signal(syscall.SIGKILL)
	run.Wait()

	for deadline := time.Now().Add(10 * time.Second); time.Now().Before(deadline); time.Sleep(200 * time.Millisecond) {
		if !running(bridgePid) {
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
