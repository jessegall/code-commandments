package csharp_test

import (
	"os"
	"os/exec"
	"path/filepath"
	"syscall"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/engine/csharp"
)

func TestTheBridgeScansCSharpIntoACodebase(t *testing.T) {
	bridge.TestRoslyn(t, os.TempDir())
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "Cart.cs"), []byte("namespace Shop;\n\npublic sealed class Cart { }\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	codebase, err := csharp.Here().Scan(root)
	if err != nil {
		t.Fatal(err)
	}
	if types := csharp.In(codebase).WhereType().Count(); types != 1 {
		t.Errorf("the scan holds %d types, not the Cart", types)
	}
}

func TestAScanAsksTheBridgeTheSessionKeepsUp(t *testing.T) {
	bridge.TestRoslyn(t)
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "Cart.cs"), []byte("namespace Shop;\n\npublic sealed class Cart { }\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	service := exec.Command("bash", "../../bridge/roslyn/roslyn-service.sh")
	service.Env = append(os.Environ(), "CLAUDE_PROJECT_DIR="+root)
	if err := service.Start(); err != nil {
		t.Fatal(err)
	}
	defer func() {
		service.Process.Signal(syscall.SIGTERM)
		service.Wait()
	}()
	for waited := 0; ; waited++ {
		if running, ok := bridge.RoslynService(root); ok {
			running.Close()
			break
		}
		if waited == 60 {
			t.Fatal("the session's bridge never answered")
		}
		time.Sleep(500 * time.Millisecond)
	}
	codebase, err := csharp.Here().Scan(root)
	if err != nil {
		t.Fatal(err)
	}
	if types := csharp.In(codebase).WhereType().Count(); types != 1 {
		t.Errorf("the service read %d types, not the Cart", types)
	}
}
