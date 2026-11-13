package hooks

import (
	"io"
	"os"
	"path/filepath"
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

	if said := Answer4Now(line); said != `{}` {
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

func TestTheRunOfAnAgentsGatheredEditsJudgesThemAsOneBatch(t *testing.T) {
	root := t.TempDir()
	settings := map[string]any{liveEditsKey: true}

	given, known := givenOf([]map[string]any{editedLine(root, "order.py", settings), editedLine(root, "refund.py", settings)})
	if !known {
		t.Fatal("two gathered edits were read as no moment at all")
	}

	if files := batchedIn(given); len(files) != 2 {
		t.Errorf("the run judges %v, want both files edited", files)
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

func TestTheEventABackgroundJudgingReadsIsKeptInAFileAndRemovedOnceRead(t *testing.T) {
	data := t.TempDir()
	t.Setenv(pluginData, data)

	event := filepath.Join(data, "events", "event-1.json")
	os.MkdirAll(filepath.Dir(event), 0o755)
	if err := os.WriteFile(event, []byte(`{"v":1,"event":"idle","agent":7}`), 0o644); err != nil {
		t.Fatal(err)
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
