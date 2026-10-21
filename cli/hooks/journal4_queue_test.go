package hooks

import (
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

// projectWithASin is a git project the hooks judge, holding one Python file that commits a sin, and the folder the
// journal gives the plugin for its own data. It answers the file's path.
func projectWithASin(t *testing.T) (root, file, data string) {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	data = t.TempDir()
	t.Setenv(pluginData, data)
	file = filepath.Join(root, "src", "refund.py")
	sinful := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"
	for path, text := range map[string]string{".commandments/config.json": `{"paths": ["src"]}`, "src/refund.py": sinful} {
		if err := os.MkdirAll(filepath.Dir(filepath.Join(root, path)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(root, path), []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	if out, err := exec.Command("git", "-C", root, "init", "-q").CombinedOutput(); err != nil {
		t.Fatalf("%s", out)
	}

	return root, file, data
}

// projectAnotherWorktreeStandsOn is a project a second worktree stands on, and the held event that asks to rebase
// it: what the shared-branch gate refuses, and the one thing the plugin still has to say before a tool runs.
func projectAnotherWorktreeStandsOn(t *testing.T) (data string, line map[string]any) {
	t.Helper()
	root, _, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)
	for _, run := range [][]string{
		{"git", "-C", root, "add", "-A"},
		{"git", "-C", root, "-c", "user.email=t@t", "-c", "user.name=T", "commit", "-qm", "first"},
		{"git", "-C", root, "worktree", "add", "-q", filepath.Join(t.TempDir(), "other"), "-b", "other"},
	} {
		if out, err := exec.Command(run[0], run[1:]...).CombinedOutput(); err != nil {
			t.Fatalf("%v: %s", run, out)
		}
	}

	return data, map[string]any{
		"v": 1, "event": "tool_starting", "session": "s1", "environment": "main",
		"data": map[string]any{"tool": "Bash", "input": map[string]any{"command": "git pull --rebase"}},
	}
}

// eventOf is the line version 4 of the journal sends for a tool and the file it names.
func eventOf(event, file string) map[string]any {
	return map[string]any{
		"v": 1, "event": event, "session": "s1", "environment": "main",
		"data": map[string]any{"tool": "Write", "input": map[string]any{"file_path": file}},
	}
}

// TestTheHeldEventIsAnsweredWithNothingWorkedOutAndSaysItToTheQueue holds the whole promise of the one event the
// journal may hold: its answer carries NOTHING worked out, and what the handlers had to say reaches the agent by the
// queue the journal drains at its next hook instead. A hook that worked its answer out on the path the journal waits
// on spent that wait on a scan, which is the fault this test exists to keep from coming back.
func TestTheHeldEventIsAnsweredWithNothingWorkedOutAndSaysItToTheQueue(t *testing.T) {
	data, line := projectAnotherWorktreeStandsOn(t)

	if answer := Answer4Now(line); answer != `{"v":1}` {
		t.Fatalf("the held event's answer worked something out: %s", answer)
	}

	Judge4Into(line, data)

	queue, _ := Queue4In(data)
	written, err := os.ReadFile(queue.Path())
	if err != nil {
		t.Fatalf("the run wrote no queue: %v", err)
	}

	var queued map[string]any
	if err := json.Unmarshal([]byte(strings.SplitN(strings.TrimSpace(string(written)), "\n", 2)[0]), &queued); err != nil {
		t.Fatalf("the journal cannot read %q: %v", written, err)
	}

	if queued["kind"] != "context" || !strings.Contains(queued["text"].(string), "worktree") {
		t.Errorf("the queue holds %v", queued)
	}
}

// TestTheAnswerIsTheSameHoweverManyFilesTheToolChanged holds the answer to the one thing a hook must never depend on:
// how much the tool did. Sixty files changed and one file changed are answered alike, because neither is judged on
// the way out.
func TestTheAnswerIsTheSameHoweverManyFilesTheToolChanged(t *testing.T) {
	root, file, _ := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)
	one := Answer4Now(eventOf("tool_starting", file))

	sinful := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"
	for at := 0; at < 60; at++ {
		if err := os.WriteFile(filepath.Join(root, "src", "file"+string(rune('a'+at%26))+string(rune('a'+at/26))+".py"), []byte(sinful), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	shell := map[string]any{
		"v": 1, "event": "tool_starting", "session": "s1", "environment": "main",
		"data": map[string]any{"tool": "Bash", "input": map[string]any{"command": "./rewrite-everything"}},
	}

	if many := Answer4Now(shell); many != one {
		t.Errorf("sixty files are answered %s and one file %s", many, one)
	}
}

// TestWithNoFolderOfItsOwnTheHookAnswersTheHeldEventInFull holds the plugin to a journal that drains no queue: with
// nowhere to park what it has to say, even the held event is answered with it, as it was before a queue existed.
func TestWithNoFolderOfItsOwnTheHookAnswersTheHeldEventInFull(t *testing.T) {
	_, line := projectAnotherWorktreeStandsOn(t)
	t.Setenv(pluginData, "")

	answer := Answer4Now(line)
	if !strings.Contains(answer, "worktree") {
		t.Errorf("without a queue the held event's answer says nothing: %s", answer)
	}
}

// TestAnEventHeardAfterTheReplySaysWhatItFound holds the other half: every event but the held one is heard AFTER the
// journal has replied to the agent, so nothing waits on its answer and the sins it found are said there and then.
// Keeping them quiet instead would cost the agent every mark it works by, for no gain in what waits.
func TestAnEventHeardAfterTheReplySaysWhatItFound(t *testing.T) {
	root, file, _ := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)

	answer := Answer4Now(eventOf("tool_finished", file))
	if !strings.Contains(answer, "redundant-python-else") {
		t.Errorf("the heard event says nothing of the sin it found: %s", answer)
	}
}

// TestAQueueLineIsOneWholeLineOfTheJournalsOwnShape holds the written shape to the contract the journal drains by: one
// JSON object per line, a card's own fields nested under `card` so neither kind collides, and every write an append,
// since the journal truncates the file under a lock after each drain.
func TestAQueueLineIsOneWholeLineOfTheJournalsOwnShape(t *testing.T) {
	data := t.TempDir()
	queue, queued := Queue4In(data)
	if !queued {
		t.Fatal("a named folder gives no queue")
	}

	advice := "Code Commandments — a sin at src/A.php:3"
	if err := queue.Tell(JournalAnswer{Whisper: &advice, Raises: []Raise{{Event: "sin-found", Brief: "array-bag at src/A.php:3", Key: "sin:a1"}}}); err != nil {
		t.Fatal(err)
	}
	if err := queue.Tell(JournalAnswer{Repented: []Repented{{Key: "sin:a1", Shown: "array-bag at src/A.php:3"}}}); err != nil {
		t.Fatal(err)
	}

	written, err := os.ReadFile(queue.Path())
	if err != nil {
		t.Fatal(err)
	}

	want := []string{
		`{"kind":"context","text":"Code Commandments — a sin at src/A.php:3"}`,
		`{"card":{"key":"sin:a1","kind":"sin-found","label":"array-bag at src/A.php:3"},"kind":"card"}`,
		`{"card":{"key":"sin:a1","kind":"sin-resolved","label":"Repented: array-bag at src/A.php:3"},"kind":"card"}`,
	}
	if lines := strings.Split(strings.TrimSuffix(string(written), "\n"), "\n"); !equalLines(lines, want) {
		t.Errorf("the queue holds\n%s\nwant\n%s", strings.Join(lines, "\n"), strings.Join(want, "\n"))
	}
}

func equalLines(got, want []string) bool {
	if len(got) != len(want) {
		return false
	}
	for at := range got {
		if got[at] != want[at] {
			return false
		}
	}

	return true
}
