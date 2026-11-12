package hooks

import (
	"encoding/json"
	"io"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli"
)

func editedLine(root, file string, settings map[string]any) map[string]any {
	return map[string]any{
		"v": 1, "event": "file_edited", "session": "s1", "environment": "main", "agent": 7, "cwd": root,
		"settings": settings,
		"data": map[string]any{
			"cwd": root, "kind": "create", "path": file,
			"checkout": map[string]any{"project_root": root, "branch": "main"},
		},
	}
}

func TestWithLiveJudgingOffAnEditIsLeftAloneEntirely(t *testing.T) {
	root := t.TempDir()
	data := t.TempDir()
	t.Setenv(pluginData, data)

	os.WriteFile(filepath.Join(root, "order.py"), []byte(olderSin), 0o644)

	off := map[string]any{liveEditsKey: false}
	line := editedLine(root, "order.py", off)

	if said := Answer4Now(line); said != `{"v":1}` {
		t.Errorf("an edit with live judging off was answered with %s; it is answered with nothing", said)
	}

	raw, _ := json.Marshal(line)
	judge4Detached(raw, line)

	if _, err := os.Stat(filepath.Join(data, windowsFolder)); err == nil {
		t.Errorf("an edit with live judging off opened a window to judge it in")
	}

	moment, _ := Moment4Of(line)
	if answer := answerWith(Stamped(moment)); answer.Whisper != nil || len(answer.Raises) > 0 || answer.JudgedMs != 0 {
		t.Errorf("an edit with live judging off was judged anyway: %+v", answer)
	}

	stop := map[string]any{"event": "hook.Stop", "env": "main", "agent": map[string]any{"cwd": root}, "settings": off}
	if answer := answerWith(stop); answer.Whisper != nil || answer.Refuse != nil || len(answer.Raises) > 0 {
		t.Errorf("a stop with live judging off was judged anyway: %+v", answer)
	}
}

func TestOneAgentsEditsGatherInOneWindowAndTheNextEditOpensANewOne(t *testing.T) {
	data := t.TempDir()
	window := EditWindowIn(data, 7)

	if !window.Gather([]byte(`{"n":1}`)) {
		t.Fatal("the first edit did not open the window")
	}

	if window.Gather([]byte(`{"n":2}`)) {
		t.Fatal("a second edit opened a window while one was open, so it would start a second judging run")
	}

	if !EditWindowIn(data, 8).Gather([]byte(`{"n":3}`)) {
		t.Fatal("another agent's edit joined this agent's window")
	}

	if took := window.Close(); len(took) != 2 {
		t.Fatalf("closing the window took %d edits, want the 2 made in it", len(took))
	}

	if !window.Gather([]byte(`{"n":4}`)) {
		t.Fatal("the first edit after the window closed did not open a new one")
	}
}

func TestAWindowWhoseRunDiedIsOpenedAgain(t *testing.T) {
	window := EditWindowIn(t.TempDir(), 7)
	window.Gather([]byte(`{"n":1}`))

	long := time.Now().Add(-2 * windowLeftOpenAfter)
	os.Chtimes(window.path(".open"), long, long)

	if !window.Gather([]byte(`{"n":2}`)) {
		t.Fatal("a window left open by a run that died is never opened again, so no edit is judged after it")
	}
}

func TestTheRunThatClosesAWindowJudgesEveryEditInItAsOneBatch(t *testing.T) {
	root := t.TempDir()
	data := t.TempDir()
	settings := map[string]any{liveEditsKey: true, editWindowKey: 0}

	window := EditWindowIn(data, 7)
	for _, file := range []string{"order.py", "refund.py"} {
		raw, _ := json.Marshal(editedLine(root, file, settings))
		window.Gather(raw)
	}

	moment, _ := Moment4Of(editedLine(root, "order.py", settings))
	given := gatheredIn(window, Stamped(moment))

	if files := batchedIn(given); len(files) != 2 {
		t.Errorf("the window's run judges %v, want both files edited in it", files)
	}

	if window.Gather([]byte(`{"n":1}`)) == false {
		t.Error("the run took the edits but left the window open")
	}
}

func TestThePluginRefusesNothing(t *testing.T) {
	root := t.TempDir()

	for _, command := range []string{"bin/commandments judge", "journal sins --changes", "git pull --rebase"} {
		given := map[string]any{"event": "hook.PreToolUse", "env": "main",
			"agent": map[string]any{"cwd": root, "role": "worker"}, "tool": map[string]any{"name": "Bash", "command": command},
			"settings": map[string]any{workerCLIKey: false}}

		if answer := answerWith(given); answer.Refuse != nil {
			t.Errorf("%q was refused: %s", command, *answer.Refuse)
		}
	}
}

func TestWithLiveJudgingOffAnEditIsNotEvenRecorded(t *testing.T) {
	root := t.TempDir()
	data := t.TempDir()
	t.Setenv(pluginData, data)

	os.WriteFile(filepath.Join(root, "order.py"), []byte(olderSin), 0o644)

	keepEditOf(editedLine(root, "order.py", map[string]any{liveEditsKey: false}))

	if kept, _ := os.ReadDir(filepath.Join(data, "worker-edits")); len(kept) != 0 {
		t.Errorf("an edit with live judging off was recorded: %v", kept)
	}
}

func TestABackgroundJudgingIsStartedThroughTheJournalsQueue(t *testing.T) {
	said := strings.Join(judgingThrough("/plugin/bin/commandments-release", "/data/events/event-1.json", 7), " ")

	if want := "plugin run --kind judge --agent 7 -- /plugin/bin/commandments-release plugin-hook --judge-event=/data/events/event-1.json"; said != want {
		t.Errorf("the plugin starts its judging as %q, want %q", said, want)
	}

	if said := strings.Join(judgingThrough("/bin/c", "/e.json", 0), " "); strings.Contains(said, "--agent") {
		t.Errorf("an event of no agent names one: %q", said)
	}
}

func TestTheEventABackgroundJudgingReadsIsKeptInAFileAndRemovedOnceRead(t *testing.T) {
	data := t.TempDir()
	t.Setenv(pluginData, data)

	event, err := keptEventIn(data, []byte(`{"v":1,"event":"idle","agent":7}`))
	if err != nil {
		t.Fatal(err)
	}

	if filepath.Dir(event) != filepath.Join(data, eventsFolder) {
		t.Errorf("the event is kept at %s, outside the plugin's events folder", event)
	}

	judgeTheEventIn(event, cli.Console{Out: io.Discard, Err: io.Discard})

	if _, err := os.Stat(event); !os.IsNotExist(err) {
		t.Error("the event file is still there after its judging read it")
	}
}

func TestWithLiveJudgingOffNoEventStartsAnything(t *testing.T) {
	root := t.TempDir()
	data := t.TempDir()
	t.Setenv(pluginData, data)
	t.Setenv("PATH", t.TempDir())
	t.Setenv(journalRoot, t.TempDir())

	off := map[string]any{liveEditsKey: false}
	for _, event := range []string{"command_starting", "tool_starting", "file_edited", "idle", "ticked"} {
		line := map[string]any{"v": 1, "event": event, "session": "s1", "environment": "main", "agent": 7, "cwd": root,
			"settings": off, "data": map[string]any{"cwd": root, "text": "git status", "path": "order.py"}}
		raw, _ := json.Marshal(line)

		judge4Detached(raw, line)

		if kept, _ := os.ReadDir(filepath.Join(data, eventsFolder)); len(kept) != 0 {
			t.Errorf("%s with live judging off kept an event to judge: %v", event, kept)
		}
	}
}

func TestAWorkersEventStartsNothingWithWorkerJudgingOff(t *testing.T) {
	root := t.TempDir()
	data := t.TempDir()
	t.Setenv(pluginData, data)
	t.Setenv("PATH", t.TempDir())
	t.Setenv(journalRoot, t.TempDir())

	for _, chosen := range []map[string]any{
		{liveEditsKey: true, workersKey: false, workerEditsKey: true},
		{liveEditsKey: true, workersKey: true, workerEditsKey: false},
	} {
		for _, event := range []string{"file_edited", "command_starting", "idle"} {
			line := map[string]any{"v": 1, "event": event, "session": "s1", "environment": "main", "agent": 9, "role": "worker",
				"cwd": root, "settings": chosen, "data": map[string]any{"cwd": root, "text": "git status", "path": "order.py"}}
			raw, _ := json.Marshal(line)

			judge4Detached(raw, line)
			keepEditOf(line)

			for _, folder := range []string{eventsFolder, windowsFolder, "worker-edits"} {
				if kept, _ := os.ReadDir(filepath.Join(data, folder)); len(kept) != 0 {
					t.Errorf("a worker's %s with %v left %s in %s", event, chosen, kept, folder)
				}
			}
		}
	}
}
