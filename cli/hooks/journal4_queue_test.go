package hooks

import (
	"bufio"
	"encoding/json"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"sort"
	"strings"
	"testing"
	"time"
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

	queued := drained(t, data)
	if len(queued) != 1 {
		t.Fatalf("the run left %d file(s) in the queue", len(queued))
	}

	if !strings.Contains(asText(queued[0]["tell"]), "worktree") {
		t.Errorf("the queue holds %v", queued[0])
	}

	if queued[0]["env"] != "main" {
		t.Errorf("the queued answer names the environment %v", queued[0]["env"])
	}
}

// drained is the queue as the journal drains it: every file it holds, oldest name first, read whole.
func drained(t *testing.T, data string) []map[string]any {
	t.Helper()
	queue, _ := Queue4In(data)
	files, err := filepath.Glob(filepath.Join(queue.Path(), "*.json"))
	if err != nil {
		t.Fatal(err)
	}
	sort.Strings(files)

	var read []map[string]any
	for _, file := range files {
		content, err := os.ReadFile(file)
		if err != nil {
			t.Fatal(err)
		}
		var said map[string]any
		if err := json.Unmarshal(content, &said); err != nil {
			t.Fatalf("the journal cannot read %s: %v", file, err)
		}
		read = append(read, said)
	}

	if left, _ := filepath.Glob(filepath.Join(queue.Path(), "*.tmp")); len(left) > 0 {
		t.Errorf("the queue holds a half-written file the drain would miss: %v", left)
	}

	return read
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

// TestAQueuedAnswerIsTheAnswersOwnShapeInAFileOfItsOwn holds the written shape to the contract the journal drains
// by: one file per answer, the same shape the journal is handed on a hook with the environment it is for, named so
// the files sort in the order they were written, and nothing left under a name a drain would read half-written.
func TestAQueuedAnswerIsTheAnswersOwnShapeInAFileOfItsOwn(t *testing.T) {
	data := t.TempDir()
	queue, queued := Queue4In(data)
	if !queued {
		t.Fatal("a named folder gives no queue")
	}

	advice := "Code Commandments — a sin at src/A.php:3"
	if err := queue.Tell(JournalAnswer{Whisper: &advice, Raises: []Raise{{Event: "sin-found", Brief: "array-bag at src/A.php:3", Key: "sin:a1"}}}, "main"); err != nil {
		t.Fatal(err)
	}
	if err := queue.Tell(JournalAnswer{Repented: []Repented{{Key: "sin:a1", Shown: "array-bag at src/A.php:3"}}}, "main"); err != nil {
		t.Fatal(err)
	}
	if err := queue.Tell(JournalAnswer{}, "main"); err != nil {
		t.Fatal(err)
	}

	said := drained(t, data)
	if len(said) != 2 {
		t.Fatalf("the queue holds %d file(s): an answer with nothing to say writes none", len(said))
	}

	if said[0]["tell"] != advice || said[0]["answer"] != "tell" || said[0]["v"] != float64(protocol4) {
		t.Errorf("the first answer reads %v", said[0])
	}

	first, _ := said[0]["cards"].([]any)
	second, _ := said[1]["cards"].([]any)
	if len(first) != 1 || len(second) != 1 {
		t.Fatalf("the cards read %v and %v", said[0]["cards"], said[1]["cards"])
	}

	raised, _ := first[0].(map[string]any)
	settled, _ := second[0].(map[string]any)
	if raised["key"] != "sin:a1" || raised["kind"] != "sin-found" || settled["key"] != raised["key"] || settled["kind"] != "sin-resolved" {
		t.Errorf("the sin was raised as %v and settled as %v", raised, settled)
	}
}

// TestTheServiceAnswersTheHeldEventWithTheGatesAloneAndAdvisesAfter holds the service to the window the journal
// waits: the line it answers carries the gates and nothing else, and the rest of the moment is handed on to be
// worked out after the answer went back. The journal allows a few milliseconds for this, which is why nothing the
// answer needs may be worked out on the way.
func TestTheServiceAnswersTheHeldEventWithTheGatesAloneAndAdvisesAfter(t *testing.T) {
	_, line := projectAnotherWorktreeStandsOn(t)
	socket := filepath.Join(t.TempDir(), "serve.sock")
	listener, err := net.Listen("unix", socket)
	if err != nil {
		t.Skipf("no socket to listen on: %v", err)
	}
	defer listener.Close()

	advised := make(chan asked, 1)
	go serve(listener, func() bool { return true }, startAdviser(func(moment asked) { advised <- moment }))

	connection, err := net.Dial("unix", socket)
	if err != nil {
		t.Fatal(err)
	}
	defer connection.Close()

	said, err := json.Marshal(line)
	if err != nil {
		t.Fatal(err)
	}
	if _, err := connection.Write(append(said, '\n')); err != nil {
		t.Fatal(err)
	}

	answer, err := bufio.NewReader(connection).ReadString('\n')
	if err != nil {
		t.Fatal(err)
	}

	var read map[string]any
	if err := json.Unmarshal([]byte(answer), &read); err != nil {
		t.Fatalf("the journal cannot read %q: %v", answer, err)
	}
	if read["answer"] != "refuse" || !strings.Contains(asText(read["reason"]), "worktree") {
		t.Errorf("the service answered %v", read)
	}

	select {
	case moment := <-advised:
		if !moment.four {
			t.Error("the moment was handed on as version 3's")
		}
	case <-time.After(5 * time.Second):
		t.Error("the moment was never handed on to be worked out")
	}
}

// TestTheWorktreesAreKeptUntilOneIsAddedOrRemoved holds the gate's own knowledge to the window: it is read from git
// once and kept, and the folder git writes a worktree in is what says the answer has changed.
func TestTheWorktreesAreKeptUntilOneIsAddedOrRemoved(t *testing.T) {
	root, _, _ := projectWithASin(t)
	for _, run := range [][]string{
		{"git", "-C", root, "add", "-A"},
		{"git", "-C", root, "-c", "user.email=t@t", "-c", "user.name=T", "commit", "-qm", "first"},
	} {
		if out, err := exec.Command(run[0], run[1:]...).CombinedOutput(); err != nil {
			t.Fatalf("%v: %s", run, out)
		}
	}

	if standing := otherWorktrees(root); len(standing) != 0 {
		t.Fatalf("a lone checkout lists %v", standing)
	}

	added := filepath.Join(t.TempDir(), "other")
	if out, err := exec.Command("git", "-C", root, "worktree", "add", "-q", added, "-b", "other").CombinedOutput(); err != nil {
		t.Fatalf("%s", out)
	}

	if standing := otherWorktrees(root); len(standing) != 1 {
		t.Errorf("after one was added the gate lists %v", standing)
	}

	if out, err := exec.Command("git", "-C", root, "worktree", "remove", added).CombinedOutput(); err != nil {
		t.Fatalf("%s", out)
	}

	if standing := otherWorktrees(root); len(standing) != 0 {
		t.Errorf("after it was removed the gate lists %v", standing)
	}
}
