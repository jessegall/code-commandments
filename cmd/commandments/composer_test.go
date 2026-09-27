package main

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge/bridgetest"
)

// TestAComposerInstallJudgesAsTheCheckoutDoes installs the package the way a consumer does — composer, from a
// repository holding only what the package ships, its script and its composer.json — into a project with no checkout
// and no Go beside it, fetches the release binary through the shim from a local release, wires the project, and
// judges copies of the PHP, Vue and Python fixtures exactly as a build run from the checkout judges them.
func TestAComposerInstallJudgesAsTheCheckoutDoes(t *testing.T) {
	for _, tool := range []string{"composer", "php", "node", "git"} {
		if _, err := exec.LookPath(tool); err != nil {
			t.Skipf("%s is not on PATH", tool)
		}
	}
	python := bridgetest.MypyPython(t)
	released := filepath.Join(t.TempDir(), "commandments")
	if out, err := exec.Command("go", "build", "-trimpath", "-ldflags", "-X main.version=9.9.9", "-o", released, ".").CombinedOutput(); err != nil {
		t.Fatalf("build: %v\n%s", err, out)
	}
	binary, err := os.ReadFile(released)
	if err != nil {
		t.Fatal(err)
	}
	server := release(t, "9.9.9", string(binary), sumOf(string(binary)))

	shipped := t.TempDir()
	copyFile(t, filepath.Join("..", "..", "bin", "commandments"), filepath.Join(shipped, "bin", "commandments"))
	copyFile(t, filepath.Join("..", "..", "composer.json"), filepath.Join(shipped, "composer.json"))

	project := t.TempDir()
	write(t, filepath.Join(project, "composer.json"), `{
    "name": "acme/shop",
    "require": {"jessegall/code-commandments": "9.9.9"},
    "repositories": [{"packagist.org": false}, {"type": "path", "url": "`+shipped+`", "options": {"symlink": false, "versions": {"jessegall/code-commandments": "9.9.9"}}}]
}`)
	if err := os.MkdirAll(filepath.Join(project, "src"), 0o755); err != nil {
		t.Fatal(err)
	}
	for _, fixture := range []string{"backend", "frontend", "python"} {
		if out, err := exec.Command("cp", "-R", filepath.Join("..", "..", "tests", "Fixtures", fixture), filepath.Join(project, "src", fixture)).CombinedOutput(); err != nil {
			t.Fatalf("copy %s: %v\n%s", fixture, err, out)
		}
	}
	env := append(os.Environ(), "HOME="+t.TempDir(), "COMPOSER_HOME="+t.TempDir(), "XDG_CACHE_HOME="+t.TempDir(), "COMMANDMENTS_RELEASES="+server.URL,
		"COMMANDMENTS_GO_BINARY=", "COMMANDMENTS_MYPY_PYTHON="+python, "GOMAXPROCS=2", "GOMEMLIMIT=3GiB", "NO_COLOR=1")
	run := func(program string, args ...string) string {
		command := exec.Command(program, args...)
		command.Dir, command.Env = project, env
		out, _ := command.CombinedOutput()

		return string(out)
	}
	run("git", "init", "-q")
	if out := run("composer", "install", "--no-interaction", "--no-progress"); !strings.Contains(out, "jessegall/code-commandments (9.9.9)") {
		t.Fatalf("composer install said\n%s", out)
	}
	if out := run("vendor/bin/commandments", "--version"); !strings.Contains(out, "9.9.9") {
		t.Fatalf("the installed tool says %q", out)
	}
	if out := run("vendor/bin/commandments", "install"); !strings.Contains(out, "synced") {
		t.Fatalf("install said\n%s", out)
	}
	judged := run("vendor/bin/commandments", "judge", "src", "--no-checklist", "--parallel=2")
	if checkout := run(released, "judge", "src", "--no-checklist", "--parallel=2"); judged != checkout {
		t.Errorf("the composer install judged\n%s\nthe checkout's build judged\n%s", judged, checkout)
	}
	for _, finding := range []string{".php:", ".vue:", ".py:"} {
		if !strings.Contains(judged, finding) {
			t.Errorf("the composer install found nothing in a %s file:\n%s", strings.Trim(finding, ".:"), judged)
		}
	}
}
