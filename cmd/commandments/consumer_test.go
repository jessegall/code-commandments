package main

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

// consumer is a project the binary works in with no PHP anywhere on its PATH: only git, the binary itself,
// and the Python the project's own language needs.
type consumer struct {
	t      *testing.T
	root   string
	binary string
	env    []string
}

// newConsumer builds the binary and lays out a Python project with no composer.json and no php on the
// PATH. The Python bridge reads through an interpreter that already has mypy, so nothing is installed.
func newConsumer(t *testing.T) consumer {
	t.Helper()

	python := os.Getenv("COMMANDMENTS_MYPY_PYTHON")
	if python == "" {
		home, _ := os.UserHomeDir()
		built, _ := filepath.Glob(filepath.Join(home, ".cache/code-commandments/mypy-bridge/*/venv/bin/python"))

		for _, candidate := range built {
			if exec.Command(candidate, "-c", "import mypy").Run() == nil {
				python = candidate

				break
			}
		}
	}

	if python == "" {
		t.Fatal("no Python with mypy to read the project with: set COMMANDMENTS_MYPY_PYTHON")
	}

	bin := t.TempDir()
	binary := filepath.Join(bin, "commandments")

	if out, err := exec.Command("go", "build", "-o", binary, ".").CombinedOutput(); err != nil {
		t.Fatalf("build: %v\n%s", err, out)
	}

	git, err := exec.LookPath("git")
	if err != nil {
		t.Fatal(err)
	}

	if err := os.Symlink(git, filepath.Join(bin, "git")); err != nil {
		t.Fatal(err)
	}

	root := t.TempDir()
	c := consumer{t, root, binary, []string{
		"PATH=" + bin,
		"HOME=" + t.TempDir(),
		"XDG_CACHE_HOME=" + t.TempDir(),
		"COMMANDMENTS_MYPY_PYTHON=" + python,
		"GOMAXPROCS=2",
		"GOMEMLIMIT=3GiB",
	}}

	c.run(0, "git", "init", "-q")
	c.run(0, "git", "-c", "user.email=a@b", "-c", "user.name=a", "commit", "-q", "--allow-empty", "-m", "init")
	c.write("src/app.py", "def run(expression: str) -> object:\n    return eval(expression)\n")

	return c
}

func (c consumer) write(path, contents string) {
	c.t.Helper()

	full := filepath.Join(c.root, path)
	if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
		c.t.Fatal(err)
	}

	if err := os.WriteFile(full, []byte(contents), 0o644); err != nil {
		c.t.Fatal(err)
	}
}

func (c consumer) read(path string) string {
	c.t.Helper()

	contents, err := os.ReadFile(filepath.Join(c.root, path))
	if err != nil {
		c.t.Fatalf("%s: %v", path, err)
	}

	return string(contents)
}

// run runs a program in the project, the binary when the program is commandments, and answers what it
// printed; an exit code other than the one expected fails the test.
func (c consumer) run(exit int, program string, args ...string) string {
	c.t.Helper()

	return c.feed(exit, "", program, args...)
}

func (c consumer) feed(exit int, stdin, program string, args ...string) string {
	c.t.Helper()

	if program == "commandments" {
		program = c.binary
	}

	command := exec.Command(program, args...)
	command.Dir, command.Env, command.Stdin = c.root, c.env, strings.NewReader(stdin)

	out, err := command.CombinedOutput()

	code := 0
	if exited, isExit := err.(*exec.ExitError); isExit {
		code = exited.ExitCode()
	} else if err != nil {
		c.t.Fatalf("%s %v: %v", program, args, err)
	}

	if code != exit {
		c.t.Fatalf("%s %v exited %d, want %d:\n%s", filepath.Base(program), args, code, exit, out)
	}

	return string(out)
}

func TestAProjectWithOnlyTheBinaryIsWiredJudgedAndKeepsItsOwnRules(t *testing.T) {
	c := newConsumer(t)

	if _, found := lookIn(c.env, "php"); found {
		t.Fatal("php is on the consumer's PATH")
	}

	synced := c.run(0, "commandments", "sync")
	if !strings.Contains(synced, "skills published to .agents/skills, read by Claude Code, Codex.") {
		t.Errorf("sync said\n%s", synced)
	}

	for _, published := range []string{".agents/skills/commandments/SKILL.md", ".agents/skills/commandments-python-absence/SKILL.md", ".claude/skills/commandments-python-absence/SKILL.md"} {
		c.read(published)
	}

	if agents := c.read("AGENTS.md"); !strings.Contains(agents, "commandments-python-absence") {
		t.Error("AGENTS.md does not brief the Python skills")
	}

	if settings := c.read(".claude/settings.json"); !strings.Contains(settings, `"command": "commandments hooks # @code-commandments-managed"`) || strings.Contains(settings, "php") {
		t.Errorf("the hooks are not wired to the binary:\n%s", settings)
	}

	if config := c.read(".commandments/config.json"); !strings.Contains(config, `"src"`) {
		t.Errorf("config.json names no source root:\n%s", config)
	}

	c.read(".commandments/config.schema.json")

	made := c.run(0, "commandments", "make", "NoEval", "--engine=python")
	if !strings.Contains(made, "NoEvalDetector.json") {
		t.Errorf("make said\n%s", made)
	}

	c.write(".commandments/custom/NoEvalDetector.json", `{"engine": "python", "sin": {"name": "no-eval", "description": "eval runs text as code", "skill": "no-eval"}, "find": {"select": "call", "where": [{"name": "eval"}]}}`)

	if synced := c.run(0, "commandments", "sync"); !strings.Contains(synced, "synced") {
		t.Errorf("resync said\n%s", synced)
	}

	if agents := c.read("AGENTS.md"); !strings.Contains(agents, "commandments-no-eval") || !strings.Contains(agents, "this project's own") {
		t.Error("AGENTS.md does not brief the project's own skill")
	}

	judged := c.run(1, "commandments", "judge", "--changes", "--no-checklist", "--parallel=2")
	if !strings.Contains(judged, "[NoEvalDetector (custom)]") || !strings.Contains(judged, "app.py:2") {
		t.Errorf("judge said\n%s", judged)
	}

	hooked := c.feed(0, `{"hook_event_name":"PostToolUse","session_id":"s1","tool_name":"Write","tool_input":{"file_path":"src/app.py"}}`, "commandments", "hooks")
	if !strings.Contains(hooked, "no-eval at") || !strings.Contains(hooked, "commandments-no-eval") {
		t.Errorf("the per-edit hook said\n%s", hooked)
	}

	stopped := c.feed(0, `{"hook_event_name":"Stop","session_id":"s1"}`, "commandments", "hooks")
	if !strings.Contains(stopped, `"decision":"block"`) {
		t.Errorf("the stop hook said\n%s", stopped)
	}

	// A project with no composer.json has no composer shim: everything names the binary itself.
	for said, text := range map[string]string{
		"AGENTS.md":                            c.read("AGENTS.md"),
		".agents/skills/commandments/SKILL.md": c.read(".agents/skills/commandments/SKILL.md"),
		"judge":                                judged,
		"the per-edit hook":                    hooked,
		"the stop hook":                        stopped,
	} {
		if strings.Contains(text, "vendor/bin") {
			t.Errorf("%s names vendor/bin, which the project does not have", said)
		}
	}

	if !strings.Contains(c.read("AGENTS.md"), "`commandments judge src`") || !strings.Contains(stopped, "`commandments judge --changes`") {
		t.Error("the briefing or the stop hook does not name the binary")
	}
}

// lookIn says where a program is on the environment's PATH, and whether it is there.
func lookIn(env []string, program string) (string, bool) {
	for _, entry := range env {
		if path, isPath := strings.CutPrefix(entry, "PATH="); isPath {
			for _, dir := range filepath.SplitList(path) {
				if _, err := os.Stat(filepath.Join(dir, program)); err == nil {
					return filepath.Join(dir, program), true
				}
			}
		}
	}

	return "", false
}
