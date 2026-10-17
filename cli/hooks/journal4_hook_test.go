package hooks

import (
	"encoding/json"
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
)

// TestEachVersion4EventIsReadAsTheMomentItStandsFor holds the translation in: every event version 4 of the journal
// sends a hook for reaches the handlers as that hook, with its session, its tool, the command and the file it names.
func TestEachVersion4EventIsReadAsTheMomentItStandsFor(t *testing.T) {
	t.Setenv("JOURNAL_ROOT", "/the/project")
	for event, hook := range hooksOf4 {
		line := map[string]any{"v": 1, "event": event, "session": "s1", "environment": "main", "data": map[string]any{"tool": "Edit", "input": map[string]any{"file_path": "src/A.php", "command": "ls"}}}
		given, known := Moment4Of(line)
		if !known {
			t.Fatalf("%s stands for no hook", event)
		}

		moment := MomentOf(given)
		if moment.Event != hook || moment.Cwd != "/the/project" || *moment.Session != "s1" || *moment.Tool != "Edit" || *moment.File != "src/A.php" || *moment.Command != "ls" || *moment.Env != "main" {
			t.Errorf("%s read as %+v", event, moment)
		}
	}

	if _, known := Moment4Of(map[string]any{"event": "tasks.created"}); known {
		t.Error("a row event was read as a hook")
	}

	if Answer4Of(map[string]any{"event": "tasks.created"}) != "" {
		t.Error("an event that stands for no hook is answered with more than allow")
	}
}

// TestTheAnswerIsSaidAsVersion4ReadsIt holds the translation out: a refusal keeps the advice beside its reason, advice
// alone is told, a sin found or repented is a card keyed by its file, and a mark settled with no card of its own gets one.
func TestTheAnswerIsSaidAsVersion4ReadsIt(t *testing.T) {
	refused, advice := "the shared branch takes no edits", "Code Commandments — a sin"
	cases := []struct {
		answer JournalAnswer
		want   string
	}{
		{JournalAnswer{}, `{"v":1}`},
		{JournalAnswer{Refuse: &refused}, `{"answer":"refuse","reason":"the shared branch takes no edits","v":1}`},
		{JournalAnswer{Refuse: &refused, Whisper: &advice}, `{"answer":"refuse","reason":"the shared branch takes no edits\n\nCode Commandments — a sin","v":1}`},
		{JournalAnswer{Whisper: &advice}, `{"answer":"tell","tell":"Code Commandments — a sin","v":1}`},
		{
			JournalAnswer{Raises: []Raise{{Event: "sin-found", Brief: "array-bag at src/A.php:3", Key: "sin:a1"}}, Settles: []string{"sin:a1", "sin:b2"}},
			`{"cards":[{"key":"sin:a1","kind":"sin-found","label":"array-bag at src/A.php:3"},{"key":"sin:b2","kind":"sin-resolved","label":"Repented"}],"v":1}`,
		},
	}

	for _, c := range cases {
		if got := c.answer.Line4(); got != c.want {
			t.Errorf("said\n%s\nwant\n%s", got, c.want)
		}
	}
}

// TestSettingsAreReadFromEitherVersionOfTheJournal holds journal-config to both: the JSON itself in JOURNAL_SETTINGS
// from version 3, a file of it from version 4, whose flags and lists come back as the text version 3 gave.
func TestSettingsAreReadFromEitherVersionOfTheJournal(t *testing.T) {
	t.Setenv("JOURNAL_SETTINGS", `{"language_php": "false", "folders_judged": "src\napp"}`)
	if chosen, given := chosenSettings(); !given || chosen["language_php"] != "false" || chosen["folders_judged"] != "src\napp" {
		t.Errorf("version 3's settings read as %v", chosen)
	}

	file := filepath.Join(t.TempDir(), "settings.json")
	written, _ := json.Marshal(map[string]any{"language_php": false, "folders_judged": []string{"src", "app"}, "judge_parallel": 3})
	_ = os.WriteFile(file, written, 0o600)
	t.Setenv("JOURNAL_SETTINGS", file)
	if chosen, given := chosenSettings(); !given || chosen["language_php"] != "false" || chosen["folders_judged"] != "src\napp" || chosen["judge_parallel"] != "3" {
		t.Errorf("version 4's settings read as %v", chosen)
	}

	t.Setenv("JOURNAL_SETTINGS", filepath.Join(t.TempDir(), "none.json"))
	if _, given := chosenSettings(); given {
		t.Error("a missing file read as settings")
	}
}

// TestTheJournalsProjectIsFoundUnderEitherVersion holds JOURNAL_ROOT to both meanings: the journal's own folder in
// version 3, the project itself in version 4.
func TestTheJournalsProjectIsFoundUnderEitherVersion(t *testing.T) {
	for given, want := range map[string]string{"/the/project/.journal": "/the/project", "/the/project": "/the/project"} {
		t.Setenv("JOURNAL_ROOT", given)
		if project, named := workspace.JournalProject(); !named || project != want {
			t.Errorf("JOURNAL_ROOT %s named the project %s", given, project)
		}
	}
}
