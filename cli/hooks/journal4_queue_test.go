package hooks

import (
	"bufio"
	"encoding/json"
	"github.com/jessegall/code-commandments/cli/git"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"sort"
	"strconv"
	"strings"
	"testing"
	"time"
)

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
		"v": 1, "event": "command_starting", "session": "s1", "environment": "main",
		"data": map[string]any{"text": "git pull --rebase", "writes": false, "position": 1, "parts": 1},
	}
}

func eventOf(event, file string) map[string]any {
	return map[string]any{
		"v": 1, "event": event, "session": "s1", "environment": "main",
		"data": map[string]any{"path": file, "kind": "edit", "added": 1, "removed": 0, "tool_use": 41},
	}
}

func TestACommandIsAnsweredWithNothingAndNeverRefused(t *testing.T) {
	data, line := projectAnotherWorktreeStandsOn(t)

	if answer := Answer4Now(line); answer != `{}` {
		t.Fatalf("a command about to run is answered %s; the plugin judges sins and refuses nothing", answer)
	}

	Judge4Into(line, data)

	for _, queued := range drained(t, data) {
		if strings.Contains(asText(queued["tell"]), "worktree") {
			t.Errorf("the queue still argues against the command: %v", queued)
		}
	}
}

func TestAFileEditedIsJudgedAndItsSinsGoToTheQueue(t *testing.T) {
	root, _, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)
	line := map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main",
		"data": map[string]any{"path": "src/refund.py", "kind": "edit", "added": 1, "removed": 0, "tool_use": 41},
	}

	Judge4Into(line, data)

	queued := drained(t, data)
	if len(queued) != 1 {
		t.Fatalf("the judge left %d file(s) in the queue", len(queued))
	}

	if !strings.Contains(asText(queued[0]["tell"]), "redundant-python-else") {
		t.Errorf("the queue names no sin: %v", queued[0])
	}

	if queued[0]["env"] != "main" {
		t.Errorf("the queued answer names the environment %v", queued[0]["env"])
	}
}

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
		read = append(read, asTheJournalSees(said))
		if err := os.Remove(file); err != nil {
			t.Fatal(err)
		}
	}

	if left, _ := filepath.Glob(filepath.Join(queue.Path(), "*.tmp")); len(left) > 0 {
		t.Errorf("the queue holds a half-written file the drain would miss: %v", left)
	}

	return read
}

// asTheJournalSees is a told answer as the plugin's script hands it to the journal (.journal-plugin/hook.py shaped,
// whose own tests hold the shape), so these tests read what an agent is told.
func asTheJournalSees(fields map[string]any) map[string]any {
	seen := map[string]any{"v": float64(1)}
	for _, routing := range []string{"env", "agent"} {
		if value, named := fields[routing]; named {
			seen[routing] = value
		}
	}

	switch refuse, whisper := fields["refuse"], fields["whisper"]; {
	case refuse != nil && whisper != nil && refuse != whisper:
		seen["answer"], seen["reason"] = "refuse", asText(refuse)+"\n\n"+asText(whisper)
	case refuse != nil:
		seen["answer"], seen["reason"] = "refuse", refuse
	case whisper != nil:
		seen["answer"], seen["tell"] = "tell", whisper
	}

	var cards []any
	raised, _ := fields["raises"].([]any)
	for _, each := range raised {
		entry, _ := each.(map[string]any)
		card := map[string]any{"type": entry["event"], "kind": entry["event"], "key": entry["key"], "label": entry["brief"]}
		if detail, named := entry["detail"]; named {
			card["detail"] = detail
		}
		cards = append(cards, card)
	}
	if len(cards) > 0 {
		seen["cards"] = cards
	}

	if required, named := fields["requires"]; named {
		seen["require_skills"] = required
	}

	return seen
}

func saidAfter(t *testing.T, data string, line map[string]any) map[string]any {
	t.Helper()
	Judge4Into(line, data)

	said := drained(t, data)
	if len(said) > 1 {
		t.Fatalf("one event left %d answers in the queue: %v", len(said), said)
	}
	if len(said) == 0 {
		return map[string]any{}
	}

	return said[0]
}

func cardsOf(said map[string]any) []map[string]any {
	raised, _ := said["cards"].([]any)

	var cards []map[string]any
	for _, each := range raised {
		card, _ := each.(map[string]any)
		cards = append(cards, card)
	}

	return cards
}

func settles(cards []map[string]any, key string) bool {
	for _, card := range cards {
		if asText(card["key"]) == key && asText(card["kind"]) == "sin-resolved" {
			return true
		}
	}

	return false
}

func TestTheAnswerIsTheSameHoweverManyFilesTheToolChanged(t *testing.T) {
	root, _, _ := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)
	one := Answer4Now(map[string]any{
		"v": 1, "event": "command_starting", "session": "s1", "environment": "main",
		"data": map[string]any{"text": "echo one", "writes": false, "position": 1, "parts": 1},
	})

	sinful := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"
	for at := 0; at < 60; at++ {
		if err := os.WriteFile(filepath.Join(root, "src", "file"+string(rune('a'+at%26))+string(rune('a'+at/26))+".py"), []byte(sinful), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	shell := map[string]any{
		"v": 1, "event": "command_starting", "session": "s1", "environment": "main",
		"data": map[string]any{"text": "./rewrite-everything", "writes": true, "position": 1, "parts": 1},
	}

	if many := Answer4Now(shell); many != one {
		t.Errorf("sixty files are answered %s and one file %s", many, one)
	}
}

func TestAnEditIsAnsweredWithoutReadingTheFileAtAll(t *testing.T) {
	root, _, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)

	clean := filepath.Join(root, "src", "orders.py")
	if err := os.WriteFile(clean, []byte("def total(order) -> int:\n    return order.total\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	sinful := Answer4Now(eventOf("file_edited", "src/refund.py"))
	if sinful != Answer4Now(eventOf("file_edited", "src/orders.py")) {
		t.Errorf("an edit of a sinful file is answered %s and of a clean one %s", sinful, Answer4Now(eventOf("file_edited", "src/orders.py")))
	}
	if strings.Contains(sinful, "redundant-python-else") || strings.Contains(sinful, "sin-found") {
		t.Errorf("the edit's own answer carries what the judge found: %s", sinful)
	}

	if said := saidAfter(t, data, eventOf("file_edited", "src/refund.py")); !strings.Contains(asText(said["tell"]), "redundant-python-else") {
		t.Errorf("the queue the agent reads names no sin: %v", said)
	}
}

func TestAQueuedAnswerIsTheAnswersOwnShapeInAFileOfItsOwn(t *testing.T) {
	data := t.TempDir()
	queue, queued := Queue4In(data)
	if !queued {
		t.Fatal("a named folder gives no queue")
	}

	advice := "Code Commandments — a sin at src/A.php:3"
	if err := queue.Tell(JournalAnswer{Whisper: &advice, Raises: []Raise{{Event: "sin-found", Brief: "1 sin in src/A.php", Key: "sins:src/A.php"}}}, "main", 0); err != nil {
		t.Fatal(err)
	}
	if err := queue.Tell(JournalAnswer{Raises: []Raise{{Event: "sin-resolved", Brief: "src/A.php is clean", Key: "sins:src/A.php"}}}, "main", 0); err != nil {
		t.Fatal(err)
	}
	if err := queue.Tell(JournalAnswer{}, "main", 0); err != nil {
		t.Fatal(err)
	}

	said := drained(t, data)
	if len(said) != 2 {
		t.Fatalf("the queue holds %d file(s): an answer with nothing to say writes none", len(said))
	}

	if said[0]["tell"] != advice || said[0]["answer"] != "tell" || said[0]["v"] != float64(1) {
		t.Errorf("the first answer reads %v", said[0])
	}

	first, _ := said[0]["cards"].([]any)
	second, _ := said[1]["cards"].([]any)
	if len(first) != 1 || len(second) != 1 {
		t.Fatalf("the cards read %v and %v", said[0]["cards"], said[1]["cards"])
	}

	raised, _ := first[0].(map[string]any)
	settled, _ := second[0].(map[string]any)
	if raised["key"] != "sins:src/A.php" || raised["kind"] != "sin-found" || settled["key"] != raised["key"] || settled["kind"] != "sin-resolved" {
		t.Errorf("the file's mark was raised as %v and settled as %v", raised, settled)
	}
}

func TestTheServiceAnswersACommandWithNothingAndAdvisesAfter(t *testing.T) {
	_, line := projectAnotherWorktreeStandsOn(t)
	short, err := os.MkdirTemp("", "s")
	if err != nil {
		t.Fatal(err)
	}
	defer os.RemoveAll(short)

	socket := filepath.Join(short, "serve.sock")
	listener, err := net.Listen("unix", socket)
	if err != nil {
		t.Fatalf("no socket to listen on: %v", err)
	}
	defer listener.Close()

	advised := make(chan asked, 1)
	go serve(listener, func() bool { return true }, startAdvisers(func(moment asked) { advised <- moment }))

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
	if len(read) != 0 {
		t.Errorf("the service answered %v; a command is answered with nothing", read)
	}

	select {
	case moment := <-advised:
		if !moment.queued {
			t.Error("the moment was handed on as version 3's")
		}
	case <-time.After(3 * batchedWithin):
		t.Error("the moment was never handed on to be worked out")
	}
}

func TestTheEditTheJournalHandsUsDecidesWhichSinsAreFresh(t *testing.T) {
	for name, test := range map[string]struct {
		wrote int
		said  bool
	}{
		"the line the sin stands on":   {2, true},
		"a line elsewhere in the file": {5, false},
	} {
		t.Run(name, func(t *testing.T) {
			root, _, data := projectWithASin(t)
			t.Setenv("JOURNAL_ROOT", root)
			for _, run := range [][]string{
				{"git", "-C", root, "add", "-A"},
				{"git", "-C", root, "-c", "user.email=t@t", "-c", "user.name=T", "commit", "-qm", "first"},
			} {
				if out, err := exec.Command(run[0], run[1:]...).CombinedOutput(); err != nil {
					t.Fatalf("%v: %s", run, out)
				}
			}

			Judge4Into(map[string]any{
				"v": 1, "event": "file_edited", "session": "s1", "environment": "main",
				"data": map[string]any{
					"path": "src/refund.py", "kind": "edit", "added": 1, "removed": 0, "tool_use": 41,
					"lines": []any{map[string]any{"kind": "add", "line": test.wrote, "text": "    if order.late:"}},
				},
			}, data)

			queued := drained(t, data)
			said := len(queued) == 1 && strings.Contains(asText(queued[0]["tell"]), "redundant-python-else")
			if said != test.said {
				t.Errorf("the sin was said: %v, want %v (%v)", said, test.said, queued)
			}
		})
	}
}

func TestASinRepentedRaisesACleanCardOfItsOwnOnTheEventThatRepentsIt(t *testing.T) {
	root, file, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)

	raised := cardsOf(saidAfter(t, data, eventOf("file_edited", "src/refund.py")))
	if len(raised) != 1 {
		t.Fatalf("the sinful edit raised %v", raised)
	}
	if asText(raised[0]["key"]) == "" {
		t.Fatal("the card raised carries no key, so nothing can settle it")
	}
	key := fileKey("src/refund.py")

	righteous := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    return order.total\n"
	if err := os.WriteFile(file, []byte(righteous), 0o644); err != nil {
		t.Fatal(err)
	}

	settled := cardsOf(saidAfter(t, data, eventOf("file_edited", "src/refund.py")))
	if !settles(settled, key) {
		t.Errorf("the repenting edit raised %v, none of it settling %s", settled, key)
	}
}

func TestASinGoneOutsideTheEditIsStillRepented(t *testing.T) {
	root, file, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)

	raised := cardsOf(saidAfter(t, data, eventOf("file_edited", "src/refund.py")))
	if len(raised) != 1 {
		t.Fatalf("the sinful edit raised %v", raised)
	}
	key := fileKey("src/refund.py")

	repaired := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    return order.total\n\n\ndef total(order) -> int:\n    return order.total\n"
	if err := os.WriteFile(file, []byte(repaired), 0o644); err != nil {
		t.Fatal(err)
	}

	elsewhere := map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main",
		"data": map[string]any{
			"path": "src/refund.py", "kind": "edit", "added": 2, "removed": 0, "tool_use": 42,
			"lines": []any{
				map[string]any{"kind": "add", "line": 7, "text": "def total(order) -> int:"},
				map[string]any{"kind": "add", "line": 8, "text": "    return order.total"},
			},
		},
	}

	settled := cardsOf(saidAfter(t, data, elsewhere))
	if !settles(settled, key) {
		t.Errorf("the edit elsewhere settled %v, none of it the sin that is gone, %s", settled, key)
	}
}

func TestGoingIdleSettlesAMarkNothingEditedAgain(t *testing.T) {
	root, file, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)

	if raised := cardsOf(saidAfter(t, data, eventOf("file_edited", "src/refund.py"))); len(raised) != 1 {
		t.Fatalf("the sinful edit raised %v", raised)
	}

	righteous := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    return order.total\n"
	if err := os.WriteFile(file, []byte(righteous), 0o644); err != nil {
		t.Fatal(err)
	}

	other := filepath.Join(root, "src", "orders.py")
	if err := os.WriteFile(other, []byte("def total(order) -> int:\n    return order.total\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if edited := saidAfter(t, data, eventOf("file_edited", "src/orders.py")); strings.Contains(asText(edited["tell"]), "sins:src/refund.py") || settles(cardsOf(edited), "sins:src/refund.py") {
		t.Fatalf("an edit elsewhere settled another file's mark, which is not what settles it: %v", edited)
	}

	if cards := settledByTheQueue(t, data, eventOf("idle", "")); !settles(cards, "sins:src/refund.py") {
		t.Errorf("going idle settled %v, none of it the mark of the file that is now clean", cards)
	}
}

func settledByTheQueue(t *testing.T, data string, line map[string]any) []map[string]any {
	t.Helper()

	Answer4Now(line)
	Judge4Into(line, data)

	var cards []map[string]any
	for _, queued := range drained(t, data) {
		cards = append(cards, cardsOf(queued)...)
	}

	return cards
}

func TestGoingIdleRaisesNothingNew(t *testing.T) {
	root, _, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)

	if raised := cardsOf(saidAfter(t, data, eventOf("file_edited", "src/refund.py"))); len(raised) != 1 {
		t.Fatalf("the sinful edit raised %v", raised)
	}

	if idle := Answer4Now(eventOf("idle", "")); strings.Contains(idle, "sin-found") || strings.Contains(idle, "sin-resolved") {
		t.Errorf("going idle with the sin still there answered %q", idle)
	}
}

func TestTheServiceAnswersEveryVersion4EventInVersion4sShape(t *testing.T) {
	projectAnotherWorktreeStandsOn(t)

	for at, event := range []string{mergeEvent, "worker_merge_result", "a_journal_event_we_never_mapped"} {
		short, err := os.MkdirTemp("", "s")
		if err != nil {
			t.Fatal(err)
		}
		defer os.RemoveAll(short)

		socket := filepath.Join(short, strconv.Itoa(at)+".sock")
		listener, err := net.Listen("unix", socket)
		if err != nil {
			t.Fatalf("no socket to listen on: %v", err)
		}

		go serve(listener, func() bool { return true }, startAdvisers(func(asked) {}))

		connection, err := net.Dial("unix", socket)
		if err != nil {
			t.Fatal(err)
		}

		said, err := json.Marshal(map[string]any{
			"v": 1, "event": event, "session": "s1", "environment": "main",
			"data": map[string]any{"text": "git pull --rebase", "writes": false, "position": 1, "parts": 1},
		})
		if err != nil {
			t.Fatal(err)
		}

		if _, err := connection.Write(append(said, '\n')); err != nil {
			t.Fatal(err)
		}

		answer, err := bufio.NewReader(connection).ReadString('\n')
		if err != nil {
			t.Fatalf("%s was not answered: %v", event, err)
		}

		if strings.TrimSpace(answer) == "" {
			connection.Close()
			listener.Close()

			continue
		}

		var read map[string]any
		if err := json.Unmarshal([]byte(answer), &read); err != nil {
			t.Fatalf("the journal cannot read %s's answer %q: %v", event, answer, err)
		}
		read = asTheJournalSees(read)

		for key := range read {
			if !slices.Contains([]string{"v", "answer", "reason", "message", "tell", "cards", "commands"}, key) {
				t.Errorf("%s was answered with %q, a key the journal's protocol does not know", event, key)
			}
		}

		connection.Close()
		listener.Close()
	}
}

func TestAnEditNamesItsOwnLinesWhateverTheJournalCallsThem(t *testing.T) {
	for name, kind := range map[string]string{"version 4's kind": "kind", "version 5's line_type": "line_type", "the journal's type": "type"} {
		t.Run(name, func(t *testing.T) {
			root, _, data := projectWithASin(t)
			t.Setenv("JOURNAL_ROOT", root)

			said := saidAfter(t, data, map[string]any{
				"v": 1, "event": "file_edited", "session": "s1", "environment": "main",
				"data": map[string]any{
					"path": "src/refund.py", "kind": "edit", "added": 1, "removed": 0, "tool_use": 41,
					"lines": []any{map[string]any{kind: "add", "line": 5, "text": "    return order.total"}},
				},
			})

			if strings.Contains(asText(said["tell"]), "redundant-python-else") {
				t.Errorf("the edit's own rows were not read, so a sin away from the edit was raised: %v", said)
			}
		})
	}
}

func TestTheJournalsMinuteBeatSweepsLikeGoingIdle(t *testing.T) {
	root, file, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)

	if raised := cardsOf(saidAfter(t, data, eventOf("file_edited", "src/refund.py"))); len(raised) != 1 {
		t.Fatalf("the sinful edit raised %v", raised)
	}

	righteous := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    return order.total\n"
	if err := os.WriteFile(file, []byte(righteous), 0o644); err != nil {
		t.Fatal(err)
	}

	if cards := settledByTheQueue(t, data, eventOf("ticked", "")); !settles(cards, "sins:src/refund.py") {
		t.Errorf("the beat settled %v, none of it the mark of the file that is now clean", cards)
	}
}

func TestAPathIsReadAgainstTheFolderTheJournalNames(t *testing.T) {
	root, _ := workerProject(t)
	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")

	t.Setenv("JOURNAL_ROOT", root)

	moment, known := Moment4Of(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 7,
		"data": map[string]any{
			"path": "src/order.py", "kind": "edit", "cwd": worktree,
			"lines": []any{map[string]any{"line_type": "add", "line": 2, "text": "    def total(self):"}},
		},
	})
	if !known {
		t.Fatal("the journal's own delivery is no moment")
	}

	named := MomentOf(moment)
	if named.File == nil {
		t.Fatal("the moment names no file at all")
	}

	own := filepath.Join(worktree, "src", "order.py")
	if *named.File != own {
		t.Errorf("the moment names %q; the journal said the path was relative to %s, so it is %s",
			*named.File, worktree, own)
	}

	if named.Cwd != worktree {
		t.Errorf("the moment's folder is %q, not the worktree the journal named", named.Cwd)
	}

	absolute, _ := Moment4Of(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 7,
		"data": map[string]any{"path": own, "kind": "edit", "cwd": worktree},
	})

	if said := MomentOf(absolute); said.File == nil || *said.File != own {
		t.Errorf("an absolute path was not left as it came: %v", said.File)
	}
}

func TestAWorkersCheckoutIsFoundHoweverDeepTheJournalKeepsIt(t *testing.T) {
	root := t.TempDir()

	resolved, err := filepath.EvalSymlinks(root)
	if err != nil {
		t.Fatal(err)
	}
	root = resolved

	deep := filepath.Join(root, ".journal", "worktrees", "development", "worker-1")

	wrote(t, root, map[string]string{"src/order.py": "class Order:\n    pass\n"})
	gitIn(t, root, "init", "-q", "-b", "main")
	gitIn(t, root, "-c", "user.email=t@t", "-c", "user.name=t", "add", "-A")
	gitIn(t, root, "-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the project")
	gitIn(t, root, "worktree", "add", "-q", "-b", "a-worker", deep)

	t.Setenv("JOURNAL_ROOT", root)

	moment, known := Moment4Of(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "development", "agent": 1,
		"data": map[string]any{"path": filepath.Join(deep, "src", "order.py"), "kind": "edit"},
	})
	if !known {
		t.Fatal("the delivery is no moment")
	}

	if said := MomentOf(moment).Cwd; said != deep {
		t.Errorf("the moment's folder is %q; the file lies in the checkout at %s", said, deep)
	}
}

func TestAPathTheMomentsFolderDoesNotHoldIsReadAgainstTheProject(t *testing.T) {
	root, _ := workerProject(t)
	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")

	t.Setenv("JOURNAL_ROOT", root)

	moment, known := Moment4Of(map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 7,
		"data": map[string]any{
			"path": filepath.Join(".journal", "worktrees", "worker-1", "src", "order.py"),
			"kind": "edit", "cwd": worktree,
			"lines": []any{map[string]any{"line_type": "add", "line": 2, "text": "    def total(self):"}},
		},
	})
	if !known {
		t.Fatal("the journal's own delivery is no moment")
	}

	named := MomentOf(moment)
	if named.File == nil {
		t.Fatal("the moment names no file at all")
	}

	own := filepath.Join(worktree, "src", "order.py")
	if *named.File != own {
		t.Errorf("the moment names %q; the worktree does not hold that path, and the project does, so it is %s",
			*named.File, own)
	}

	if named.Cwd != worktree {
		t.Errorf("the moment's folder is %q, not the worktree the file lies in", named.Cwd)
	}
}

func TestATickIsAnsweredWithTheGatesAloneAndSettlesThroughTheQueue(t *testing.T) {
	root, _, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)

	sinful := "class Refund:\n    def total(self):\n        if True:\n            return 1\n        else:\n            return 2\n"

	for at, event := range []string{tickedEvent, idleEvent} {
		agent := 7 + at
		if err := os.WriteFile(filepath.Join(root, "src", "refund.py"), []byte(sinful), 0o644); err != nil {
			t.Fatal(err)
		}

		Judge4Into(map[string]any{
			"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": agent,
			"data": map[string]any{"path": "src/refund.py", "kind": "edit", "added": 1, "removed": 0, "tool_use": 41},
		}, data)

		if queued := drained(t, data); len(queued) == 0 {
			t.Fatalf("%s: the edit told nothing, so there is no sin for it to settle", event)
		}

		if err := os.WriteFile(filepath.Join(root, "src", "refund.py"), []byte("class Refund:\n    pass\n"), 0o644); err != nil {
			t.Fatal(err)
		}

		line := map[string]any{
			"v": 1, "event": event, "session": "s1", "environment": "main", "agent": agent,
			"data": map[string]any{},
		}

		answer := Answer4Now(line)
		for _, worked := range []string{"cards", "raise", "settle"} {
			if strings.Contains(answer, worked) {
				t.Errorf("%s is answered with %s worked out on the path the journal waits on: %s", event, worked, answer)
			}
		}

		Judge4Into(line, data)

		var said []string
		for _, queued := range drained(t, data) {
			written, _ := json.Marshal(queued)
			said = append(said, string(written))
		}

		if len(said) == 0 {
			t.Errorf("%s said nothing at all, so the sin it should have settled stays orange for good", event)
		}
	}
}

func TestAFileTheToolWroteWholeIsReadWhicheverWordTheEventUses(t *testing.T) {
	for name, word := range map[string]string{"version 4's kind": "kind", "the journal's type": "type"} {
		t.Run(name, func(t *testing.T) {
			said := MomentOf(map[string]any{
				"event": "hook.file_edited", "agent": map[string]any{"cwd": t.TempDir()},
				"data": map[string]any{"path": "src/refund.py", word: "create", "added": 5, "removed": 0},
			})

			if !said.ChangedWhole {
				t.Fatalf("a file the tool wrote whole, said as %q, reads as an edit of a file that was already there", word)
			}

			if whole, _ := said.HookPayload()[changedKey].(map[string]any); whole["whole"] != true {
				t.Errorf("the payload says %v, not that the whole file is the edit's", said.HookPayload()[changedKey])
			}
		})
	}
}

func TestWhatWasNotAskedTravelsTheQueueToTheAgent(t *testing.T) {
	root, _, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)

	project := git.ProjectRoot(root)
	kept.Drop(project, git.CurrentBranch(project))

	line := map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main",
		"data": map[string]any{"path": "src/refund.py", "kind": "edit", "added": 1, "removed": 0, "tool_use": 42},
	}

	Judge4Into(line, data)

	queued := drained(t, data)
	if len(queued) != 1 {
		t.Fatalf("the judge left %d file(s) in the queue", len(queued))
	}

	said := asText(queued[0]["tell"])
	for _, want := range []string{"read across files were not asked", "not a clean answer"} {
		if !strings.Contains(said, want) {
			t.Errorf("the queued answer does not say %q:\n%s", want, said)
		}
	}
}

func TestAJudgingForAWorkerThatIsGoneTellsNobody(t *testing.T) {
	root, _, data := projectWithASin(t)
	t.Setenv("JOURNAL_ROOT", root)

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-9")
	if err := os.MkdirAll(filepath.Join(worktree, "src"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(worktree, "src", "refund.py"),
		[]byte("def refund(order):\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	line := map[string]any{
		"v": 1, "event": "file_edited", "session": "s9", "environment": "main", "agent": 9,
		"cwd":  worktree,
		"data": map[string]any{"path": "src/refund.py", "cwd": worktree, "kind": "edit", "added": 1, "removed": 0},
	}

	if err := os.RemoveAll(worktree); err != nil {
		t.Fatal(err)
	}

	Judge4Into(line, data)

	if queued := drained(t, data); len(queued) != 0 {
		t.Errorf("a judging for a worker whose folder is gone left %d file(s) in the queue: %v", len(queued), queued)
	}
}

func TestABatchStandsOnAMomentThatNamesAFile(t *testing.T) {
	edited := "/project/src/order.py"

	ticked := asked{given: map[string]any{"event": "ticked", "agent": map[string]any{"id": 3}}}
	wrote := asked{given: map[string]any{
		"event": "PostToolUse",
		"agent": map[string]any{"id": 3},
		"tool":  map[string]any{"name": "Edit", "file": edited},
	}}

	stands := batched([]asked{ticked, wrote})

	moment := MomentOf(stands.given)
	if moment.File == nil || *moment.File != edited {
		t.Errorf("a tick that arrived first carried the batch, so the edit beside it was judged by nobody: %+v", moment)
	}
}

func TestEveryFileOfABatchReachesTheJudging(t *testing.T) {
	first, second := "/project/src/order.py", "/project/src/refund.py"

	stands := batched([]asked{
		{given: map[string]any{"event": "PostToolUse", "agent": map[string]any{"id": 4},
			"tool": map[string]any{"name": "Edit", "file": first}}},
		{given: map[string]any{"event": "PostToolUse", "agent": map[string]any{"id": 4},
			"tool": map[string]any{"name": "Edit", "file": second}}},
	})

	carried := batchedIn(MomentOf(stands.given).HookPayload())

	if len(carried) != 2 || carried[0] != first || carried[1] != second {
		t.Errorf("the judging is told about %v of the two files the batch held, so the rest are judged by nobody", carried)
	}
}
