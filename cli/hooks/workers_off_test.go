package hooks

import (
	"encoding/json"
	"path/filepath"
	"strings"
	"testing"
)

func settingsOf(t *testing.T, chosen map[string]any) {
	t.Helper()

	said, err := json.Marshal(chosen)
	if err != nil {
		t.Fatal(err)
	}

	t.Setenv(journalSettings, string(said))
}

func TestAWorkersEditIsNotJudgedAndTheAnswerNamesTheSetting(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)
	settingsOf(t, map[string]any{workersKey: "false"})

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(root, under), olderSin)

	said := Answer4Of(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 7,
		"cwd": root, "role": "worker",
		"data": map[string]any{"cwd": root, "kind": "create", "path": under},
	})

	if !strings.Contains(said, workersKey) {
		t.Fatalf("a worker's edit was answered without naming the setting that turned it off: %s", said)
	}

	if strings.Contains(said, "deep-python-nesting") || strings.Contains(said, "redundant-python-else") {
		t.Errorf("a worker's edit was judged although the setting is off: %s", said)
	}

}

func TestAMainAgentsEditIsJudgedWhateverTheWorkersSettingSays(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)
	settingsOf(t, map[string]any{workersKey: "false"})

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(root, under), olderSin)

	said := Answer4Of(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 1,
		"cwd": root, "role": "main",
		"data": map[string]any{"cwd": root, "kind": "create", "path": under},
	})

	if strings.Contains(said, workersKey) {
		t.Fatalf("a main agent's edit was turned away by the workers setting: %s", said)
	}
}

func TestAWorkerIsJudgedWhenTheSettingIsOn(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)
	settingsOf(t, map[string]any{workersKey: "true", workerEditsKey: "true"})

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(root, under), olderSin)

	said := Answer4Of(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 7,
		"cwd": root, "role": "worker",
		"data": map[string]any{"cwd": root, "kind": "create", "path": under},
	})

	if strings.Contains(said, workersKey) {
		t.Errorf("a worker was turned away although the setting says to judge workers: %s", said)
	}
}

func TestAnEventWithNoRoleIsJudgedAsBefore(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)
	settingsOf(t, map[string]any{workersKey: "false"})

	under := filepath.Join("src", "made.py")
	wroteInto(t, filepath.Join(root, under), olderSin)

	said := Answer4Of(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 1,
		"cwd":  root,
		"data": map[string]any{"cwd": root, "kind": "create", "path": under},
	})

	if strings.Contains(said, workersKey) {
		t.Errorf("a line that names no role was turned away, and an event with no agent has an empty role: %s", said)
	}
}
