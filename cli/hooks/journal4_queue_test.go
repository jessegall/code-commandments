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

// eventOf is the line version 4 of the journal sends for a tool that has just written the file.
func eventOf(root, file string) map[string]any {
	return map[string]any{
		"v": 1, "event": "tool_finished", "session": "s1", "environment": "main",
		"data": map[string]any{"tool": "Write", "input": map[string]any{"file_path": file}},
	}
}

// TestTheHookAnswersWithoutJudgingAndTheJudgeWritesToTheQueue holds the whole promise of the plugin holding no hook:
// the answer the journal is handed carries NOTHING worked out, however sinful the file it was handed, and the sins
// reach the agent by the queue the journal drains at its next hook. A hook that judged on its answer path spent the
// journal's ten seconds on a scan, which is what this test exists to keep from coming back.
func TestTheHookAnswersWithoutJudgingAndTheJudgeWritesToTheQueue(t *testing.T) {
	root, file, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)
	line := eventOf(root, file)

	if answer := Answer4Now(line); answer != `{"v":1}` {
		t.Fatalf("the answer path worked something out: %s", answer)
	}

	Judge4Into(line, data)

	queue, _ := Queue4In(data)
	written, err := os.ReadFile(queue.Path())
	if err != nil {
		t.Fatalf("the judge wrote no queue: %v", err)
	}

	var kinds []string
	var said string
	for _, raw := range strings.Split(strings.TrimSpace(string(written)), "\n") {
		var queued map[string]any
		if err := json.Unmarshal([]byte(raw), &queued); err != nil {
			t.Fatalf("the journal cannot read %q: %v", raw, err)
		}
		kinds = append(kinds, queued["kind"].(string))
		if text, is := queued["text"].(string); is {
			said += text
		}
		if card, is := queued["card"].(map[string]any); is {
			if card["kind"] == "" || card["key"] == "" || card["label"] == "" {
				t.Errorf("a card line names no kind, key or label: %v", card)
			}
		}
	}

	if len(kinds) < 2 || kinds[0] != "context" || kinds[1] != "card" {
		t.Fatalf("the queue holds %v", kinds)
	}

	if !strings.Contains(said, "redundant-python-else") {
		t.Errorf("the context names no sin: %q", said)
	}
}

// TestTheAnswerIsTheSameHoweverManyFilesTheToolChanged holds the answer to the one thing a hook must never depend on:
// how much the tool did. Sixty files changed and one file changed are answered alike, because neither is judged on
// the way out.
func TestTheAnswerIsTheSameHoweverManyFilesTheToolChanged(t *testing.T) {
	root, file, _ := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)
	one := Answer4Now(eventOf(root, file))

	sinful := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"
	for at := 0; at < 60; at++ {
		if err := os.WriteFile(filepath.Join(root, "src", "file"+string(rune('a'+at%26))+string(rune('a'+at/26))+".py"), []byte(sinful), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	shell := map[string]any{
		"v": 1, "event": "tool_finished", "session": "s1", "environment": "main",
		"data": map[string]any{"tool": "Bash", "input": map[string]any{"command": "./rewrite-everything"}},
	}

	if many := Answer4Now(shell); many != one {
		t.Errorf("sixty files are answered %s and one file %s", many, one)
	}
}

// TestWithNoFolderOfItsOwnTheHookAnswersWithEverythingItFound holds the plugin to a journal that drains no queue: with
// nowhere to park a sin, the answer carries it, as it did before a queue existed.
func TestWithNoFolderOfItsOwnTheHookAnswersWithEverythingItFound(t *testing.T) {
	root, file, _ := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)
	t.Setenv(pluginData, "")

	answer := Answer4Now(eventOf(root, file))
	if !strings.Contains(answer, "redundant-python-else") {
		t.Errorf("without a queue the answer names no sin: %s", answer)
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
