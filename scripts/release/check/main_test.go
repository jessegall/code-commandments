package main

import (
	"debug/elf"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

// release is a release folder holding one binary built from a tiny program for the platform, and a budget file
// giving it the budget.
func release(t *testing.T, platform string, budget int64) (dir, budgets string) {
	t.Helper()
	dir = t.TempDir()
	source := filepath.Join(t.TempDir(), "main.go")
	if err := os.WriteFile(source, []byte("package main\n\nfunc main() {}\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	goos, goarch, _ := strings.Cut(platform, "-")
	build := exec.Command("go", "build", "-trimpath", "-o", filepath.Join(dir, "commandments-"+platform), source)
	build.Env = append(os.Environ(), "CGO_ENABLED=0", "GOOS="+goos, "GOARCH="+goarch)
	if out, err := build.CombinedOutput(); err != nil {
		t.Fatalf("build: %v\n%s", err, out)
	}
	budgets = writeBudget(t, platform, budget)

	return dir, budgets
}

func writeBudget(t *testing.T, platform string, budget int64) string {
	t.Helper()
	path := filepath.Join(t.TempDir(), "size-budget.json")
	if err := writeBudgets(path, Budgets{Budget: map[string]int64{platform: budget}, Measured: map[string]int64{}}); err != nil {
		t.Fatal(err)
	}

	return path
}

func TestAStaticBinaryWithinItsBudgetPassesAndIsSummed(t *testing.T) {
	dir, budgets := release(t, "linux-amd64", 64<<20)
	problems, err := check(dir, budgets, true)
	if err != nil || len(problems) > 0 {
		t.Fatalf("the release fails: %v %v", problems, err)
	}
	sums, _ := os.ReadFile(filepath.Join(dir, "SHA256SUMS"))
	if !strings.HasSuffix(strings.TrimSpace(string(sums)), "  commandments-linux-amd64") {
		t.Errorf("SHA256SUMS holds %q", sums)
	}
	recorded, err := readBudgets(budgets)
	if err != nil || recorded.Measured["linux-amd64"] == 0 {
		t.Errorf("the measured size is not recorded: %v %v", recorded.Measured, err)
	}
}

func TestABinaryOverItsBudgetFails(t *testing.T) {
	dir, budgets := release(t, "darwin-arm64", 1024)
	problems, err := check(dir, budgets, false)
	if err != nil || len(problems) != 1 || !strings.Contains(problems[0], "over its budget") {
		t.Errorf("the release answers %v %v", problems, err)
	}
}

func TestABinaryWithNoBudgetFails(t *testing.T) {
	dir, _ := release(t, "windows-amd64", 0)
	problems, err := check(dir, writeBudget(t, "linux-amd64", 64<<20), false)
	if err != nil || len(problems) != 1 || !strings.Contains(problems[0], "has no size budget") {
		t.Errorf("the release answers %v %v", problems, err)
	}
}

func TestALinuxBinaryThatNeedsALoaderFails(t *testing.T) {
	shell, err := elf.Open("/bin/sh")
	if err != nil {
		t.Skip("no ELF shell to stand in for a dynamically linked binary")
	}
	shell.Close()
	dir := t.TempDir()
	content, _ := os.ReadFile("/bin/sh")
	if err := os.WriteFile(filepath.Join(dir, "commandments-linux-amd64"), content, 0o755); err != nil {
		t.Fatal(err)
	}
	problems, err := check(dir, writeBudget(t, "linux-amd64", 64<<20), false)
	if err != nil || len(problems) != 1 || !strings.Contains(problems[0], "is not static") {
		t.Errorf("the release answers %v %v", problems, err)
	}
}

// TestACSharpBridgeIsSummedAndHeldToItsOwnBudget holds a release's C# bridge to a budget of its own, under its whole
// name, beside the tool's, and never asks it to be static: it is .NET's executable, not Go's.
func TestACSharpBridgeIsSummedAndHeldToItsOwnBudget(t *testing.T) {
	dir, budgets := release(t, "linux-amd64", 64<<20)
	if err := os.WriteFile(filepath.Join(dir, "roslyn-bridge-linux-amd64"), make([]byte, 2048), 0o755); err != nil {
		t.Fatal(err)
	}
	if problems, _ := check(dir, budgets, false); len(problems) != 1 || !strings.Contains(problems[0], "roslyn-bridge-linux-amd64 has no size budget") {
		t.Fatalf("a bridge with no budget passes: %v", problems)
	}
	written, err := readBudgets(budgets)
	if err != nil {
		t.Fatal(err)
	}
	written.Budget["roslyn-bridge-linux-amd64"] = 4096
	if err := writeBudgets(budgets, written); err != nil {
		t.Fatal(err)
	}
	if problems, err := check(dir, budgets, false); err != nil || len(problems) > 0 {
		t.Fatalf("the release fails: %v %v", problems, err)
	}
	if sums, _ := os.ReadFile(filepath.Join(dir, "SHA256SUMS")); !strings.Contains(string(sums), "  roslyn-bridge-linux-amd64\n") {
		t.Errorf("SHA256SUMS holds %q", sums)
	}
}
