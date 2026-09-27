package main

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
)

// TestALoneBinaryJudgesAsTheCheckoutDoes builds the binary as a release does — its source paths trimmed and its
// version stamped — and runs it where there is no checkout: a fresh cache, a PATH holding only the runtimes the
// fixtures' languages need, and the fixtures copied out. It judges them exactly as a plain build run from the
// checkout does, so nothing the binary reads lives anywhere but inside it.
func TestALoneBinaryJudgesAsTheCheckoutDoes(t *testing.T) {
	python := bridge.TestMypyPython(t)
	bin := t.TempDir()
	lone := filepath.Join(bin, "commandments")
	if out, err := exec.Command("go", "build", "-trimpath", "-ldflags", "-X main.version=v0.0.0-lone", "-o", lone, ".").CombinedOutput(); err != nil {
		t.Fatalf("build: %v\n%s", err, out)
	}
	plain := filepath.Join(t.TempDir(), "commandments")
	if out, err := exec.Command("go", "build", "-o", plain, ".").CombinedOutput(); err != nil {
		t.Fatalf("build: %v\n%s", err, out)
	}
	for _, runtime := range []string{"php", "node", "git"} {
		found, err := exec.LookPath(runtime)
		if err != nil {
			t.Skipf("%s is not on PATH", runtime)
		}
		if err := os.Symlink(found, filepath.Join(bin, runtime)); err != nil {
			t.Fatal(err)
		}
	}
	project := t.TempDir()
	for _, fixture := range []string{"backend", "frontend", "python"} {
		if out, err := exec.Command("cp", "-R", filepath.Join("..", "..", "tests", "Fixtures", fixture), filepath.Join(project, fixture)).CombinedOutput(); err != nil {
			t.Fatalf("copy %s: %v\n%s", fixture, err, out)
		}
	}
	judge := func(binary string, path ...string) string {
		command := exec.Command(binary, "judge", project, "--no-checklist", "--parallel=2")
		command.Dir = project
		command.Env = []string{"PATH=" + strings.Join(path, string(os.PathListSeparator)), "HOME=" + t.TempDir(), "XDG_CACHE_HOME=" + t.TempDir(), "COMMANDMENTS_MYPY_PYTHON=" + python, "GOMAXPROCS=2", "GOMEMLIMIT=3GiB", "NO_COLOR=1"}
		out, _ := command.CombinedOutput()

		return string(out)
	}
	alone := judge(lone, bin)
	if checkout := judge(plain, os.Getenv("PATH")); alone != checkout {
		t.Errorf("the lone binary judged\n%s\nthe checkout's build judged\n%s", alone, checkout)
	}
	for _, fixture := range []string{"backend/", "frontend/", "python/"} {
		if !strings.Contains(alone, fixture) {
			t.Errorf("the lone binary found nothing in %s:\n%s", fixture, alone)
		}
	}
	if version, _ := exec.Command(lone, "--version").Output(); !strings.Contains(string(version), "v0.0.0-lone") {
		t.Errorf("the stamped binary says it is %q", version)
	}
}
