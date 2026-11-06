package hooks

import (
	"path/filepath"
	"regexp"
	"strings"
	"testing"
)

var workerMarkForm = regexp.MustCompile(`Code Commandments — ([\w./-]+\.py):(\d+(?:,\d+)*) ([a-z-]+)(?: \(([^)]*)\))? — load ` + "`" + `([\w-]+)` + "`")

func TestAWorkerIsToldAFileAndLineInItsOwnWorktreeWithTheSkill(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)
	settingsOf(t, map[string]any{workersKey: "true"})

	worktree := stressWorktreeIn(root, 25)
	cutInto(t, root, worktree, "feature/told-a-mark")

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(worktree, under), olderSin)

	said := Answer4Of(map[string]any{
		"v": 1, "event": "file_edited", "session": "s7", "environment": "main", "agent": 7,
		"cwd": worktree, "role": "worker",
		"data": map[string]any{
			"cwd": worktree, "kind": "create", "path": under,
			"checkout": map[string]any{
				"kind": "worktree", "root": worktree, "project_root": root,
				"branch": "feature/told-a-mark", "base_commit": "aaaaaaa", "exists": true,
			},
		},
	})

	if said == "" {
		t.Fatal("a worker editing in its own worktree was told nothing at all, and this task is the one that decides whether the plan was worth doing")
	}

	found := workerMarkForm.FindStringSubmatch(said)
	if found == nil {
		t.Fatalf("what the worker is told is not in the form the journal showed us works:\n%s", said)
	}

	if filepath.IsAbs(found[1]) || strings.Contains(found[1], root) {
		t.Errorf("the file is named %q, and a worker opens a path inside its own worktree", found[1])
	}

	if found[1] != under {
		t.Errorf("the file is named %q, and the worker changed %q", found[1], under)
	}

	if found[2] == "0" || found[2] == "" {
		t.Errorf("the line is named %q", found[2])
	}

	if found[3] == "" {
		t.Errorf("the sin is not named: %s", said)
	}

	if !strings.HasPrefix(found[5], "commandments-") {
		t.Errorf("the skill is named %q, and a skill of ours is named commandments-<engine>-<subject>", found[5])
	}

	if !strings.Contains(said, "fix it at its source") && !strings.Contains(said, "each to fix at its source") {
		t.Errorf("the mark does not say to fix it at its source: %s", said)
	}
}

func TestTheWorkersAnswerSaysWhichRulesWereAsked(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)
	settingsOf(t, map[string]any{workersKey: "true"})

	worktree := stressWorktreeIn(root, 26)
	cutInto(t, root, worktree, "feature/told-what-was-asked")

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(worktree, under), olderSin)

	said := Answer4Of(map[string]any{
		"v": 1, "event": "file_edited", "session": "s7", "environment": "main", "agent": 7,
		"cwd": worktree, "role": "worker",
		"data": map[string]any{"cwd": worktree, "kind": "create", "path": under},
	})

	if said == "" {
		t.Fatal("the worker was told nothing")
	}

	if strings.Contains(said, "rules that answer from one file") || strings.Contains(said, "not a clean answer") {
		return
	}

	t.Logf("the answer carries no shortfall, which means every rule was asked: %s", said)
}
