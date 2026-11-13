package hooks

import (
	"encoding/json"
	"io"
	"os"
	"path/filepath"
	"strings"
	"testing"

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

	moment, _ := Moment4Of(line)
	if answer := answerWith(Stamped(moment)); answer.Whisper != nil || len(answer.Raises) > 0 || answer.JudgedMs != 0 {
		t.Errorf("an edit with live judging off was judged anyway: %+v", answer)
	}

	stop := map[string]any{"event": "hook.Stop", "env": "main", "agent": map[string]any{"cwd": root}, "settings": off}
	if answer := answerWith(stop); answer.Whisper != nil || answer.Refuse != nil || len(answer.Raises) > 0 {
		t.Errorf("a stop with live judging off was judged anyway: %+v", answer)
	}
}

func TestTheRunThatClosesAWindowJudgesEveryEditInItAsOneBatch(t *testing.T) {
	root := t.TempDir()
	data := t.TempDir()
	settings := map[string]any{liveEditsKey: true, editWindowKey: 0}

	window := EditWindowIn(data, 7)
	var edits []byte
	for _, file := range []string{"order.py", "refund.py"} {
		raw, _ := json.Marshal(editedLine(root, file, settings))
		edits = append(append(edits, raw...), '\n')
	}
	os.MkdirAll(window.folder, 0o755)
	os.WriteFile(window.path(".edits"), edits, 0o644)
	os.WriteFile(window.path(".open"), nil, 0o644)

	moment, _ := Moment4Of(editedLine(root, "order.py", settings))
	given, _ := gatheredIn(window, Stamped(moment))

	if files := batchedIn(given); len(files) != 2 {
		t.Errorf("the window's run judges %v, want both files edited in it", files)
	}

	if window.IsOpen() {
		t.Error("the run took the edits but left the window open")
	}
}

func TestThePluginRefusesNothing(t *testing.T) {
	root := t.TempDir()

	for _, command := range []string{"bin/commandments judge", "journal sins --changes", "git pull --rebase"} {
		given := map[string]any{"event": "hook.PreToolUse", "env": "main",
			"agent": map[string]any{"cwd": root, "role": "worker"}, "tool": map[string]any{"name": "Bash", "command": command},
			"settings": map[string]any{helpersCLIKey: false}}

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
			keepEditOf(line)

			for _, folder := range []string{"worker-edits"} {
				if kept, _ := os.ReadDir(filepath.Join(data, folder)); len(kept) != 0 {
					t.Errorf("a worker's %s with %v left %s in %s", event, chosen, kept, folder)
				}
			}
		}
	}
}

func TestAWorkerRunningTheCLIIsRefusedAtOnceWhenTheSettingDeniesIt(t *testing.T) {
	t.Setenv(pluginData, t.TempDir())

	for role, refused := range map[string]bool{"worker": true, "main": false} {
		line := map[string]any{"v": 1, "event": "command_starting", "session": "s1", "environment": "main", "agent": 9, "role": role,
			"settings": map[string]any{helpersCLIKey: false}, "data": map[string]any{"text": ".journal/plugins/code-commandments/bin/commandments-release rule try x"}}

		said := Answer4Now(line)
		if refused != strings.Contains(said, `"refuse"`) {
			t.Errorf("a %s running the CLI with %s off was answered %s", role, helpersCLIKey, said)
		}
	}
}
