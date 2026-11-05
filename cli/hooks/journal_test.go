package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"sync"
	"testing"
	"time"
)

// TestAdviceIsQueuedInTheMomentsEnvironment holds the queue to what the journal runs: bare commands, recorded in
// testdata/queued, a found sin raised under its key and a repented one settled by it, in the queue of the moment's environment beside the one the journal named. A line naming an
// environment is refused, so none does.
func TestAdviceIsQueuedInTheMomentsEnvironment(t *testing.T) {
	whisper := "Code Commandments — the edit: it breaks a rule that is described at such length that its first line runs well past eighty characters.\n  • it's here\n\n    and here"
	folder := t.TempDir()
	named := filepath.Join(folder, "code-commandments.queue")

	answer := JournalAnswer{
		Whisper: &whisper,
		Raises:  []Raise{{Event: "sin-found", Brief: "array-bag at src/A.php:3", Open: "sins/x", Key: "sin:a1"}, {Event: "sin-resolved", Brief: "a\nb"}},
		Settles: []string{"sin:b2"},
	}

	if err := (Queue{named}).Tell(answer, MomentOf(map[string]any{"event": "hook.PostToolUse", "env": "o'brien"})); err != nil {
		t.Fatal(err)
	}

	want, _ := os.ReadFile("testdata/queued")
	got, _ := os.ReadFile(filepath.Join(folder, "code-commandments.o'brien.queue"))

	if string(got) != string(want) || strings.Contains(string(got), "--env") {
		t.Errorf("queued\n%s\nwant\n%s", got, want)
	}

	if _, err := os.Stat(named); !os.IsNotExist(err) {
		t.Errorf("the default environment's queue was written: %v", err)
	}

	if (Queue{named}).For(MomentOf(map[string]any{"env": "../main"})).path != named {
		t.Error("an environment no file name can carry left the queue as named")
	}
}

func TestAdviceOfNoEnvironmentKeepsTheQueueTheJournalNamed(t *testing.T) {
	named := filepath.Join(t.TempDir(), "code-commandments.queue")
	whisper := "Code Commandments — a sin"

	if err := (Queue{named}).Tell(JournalAnswer{Whisper: &whisper}, MomentOf(map[string]any{"event": "hook.PostToolUse"})); err != nil {
		t.Fatal(err)
	}

	if got, _ := os.ReadFile(named); !strings.HasPrefix(string(got), "nudge create ") {
		t.Errorf("queued %q", got)
	}
}

// TestAdviceIsQueuedWhereThePayloadSays holds the answer to the queue the journal names for the moment's own
// environment in plugin.queue: a helper's edit in its worktree is answered in the helper's environment, never in
// the one the service started in, whose queue $JOURNAL_QUEUE named.
func TestAdviceIsQueuedWhereThePayloadSays(t *testing.T) {
	whisper := "Code Commandments — the edit breaks a rule."
	folder := t.TempDir()
	named, helpers := filepath.Join(folder, "code-commandments.queue"), filepath.Join(folder, "helper", "code-commandments.queue")
	if err := os.MkdirAll(filepath.Dir(helpers), 0o755); err != nil {
		t.Fatal(err)
	}
	answer := JournalAnswer{Whisper: &whisper, Raises: []Raise{{Event: "sin-resolved", Brief: "array-bag at src/A.vue:3"}}}
	moment := MomentOf(map[string]any{"event": "hook.PostToolUse", "env": "main-mies-buildwright", "plugin": map[string]any{"queue": helpers}})

	if err := (Queue{named}).Tell(answer, moment); err != nil {
		t.Fatal(err)
	}

	if written, _ := os.ReadFile(helpers); !strings.Contains(string(written), "sin-resolved") {
		t.Errorf("the helper's queue holds %q", written)
	}
	for _, other := range []string{named, filepath.Join(folder, "code-commandments.main-mies-buildwright.queue")} {
		if _, err := os.Stat(other); !os.IsNotExist(err) {
			t.Errorf("%s was written: %v", filepath.Base(other), err)
		}
	}
}

// TestAConfigChangeEndsTheServiceThatHoldsTheTree holds the one thing that keeps a held tree honest about the
// project's own settings. A base is read with the excludes, roots and languages in force at the time and is
// keyed by nothing else, so a newly excluded folder or a dropped root would stay in the program it answers from.
// What saves it is that the service ENDS when the config moves, and the tree goes with the process, so the next
// one reads the tree the new settings describe.
//
// Held here because it is the only thing that saves it: were the service to stop watching the config, or to keep
// serving across a change, a held base would answer from settings the project has replaced and nothing would say
// so.
func TestAConfigChangeEndsTheServiceThatHoldsTheTree(t *testing.T) {
	root := t.TempDir()

	settings := filepath.Join(root, ".commandments", "config.json")
	if err := os.MkdirAll(filepath.Dir(settings), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(settings, []byte(`{"paths": ["src"]}`), 0o644); err != nil {
		t.Fatal(err)
	}

	was := stamp(root)
	if was == 0 {
		t.Fatal("the service watches nothing of this project, so nothing would end it")
	}

	time.Sleep(10 * time.Millisecond)
	if err := os.WriteFile(settings, []byte(`{"paths": ["src"], "exclude": ["src/generated"]}`), 0o644); err != nil {
		t.Fatal(err)
	}

	if stamp(root) == was {
		t.Error("the project's config changed and the service reads the same stamp, so it serves on with a tree read under the settings before it")
	}

	// A rule of the project's own counts too: it is what a judging reads, so a tree held under the one before it
	// is a tree held under rules the project has replaced.
	own := filepath.Join(root, ".commandments", "custom", "OwnRule.json")
	if err := os.MkdirAll(filepath.Dir(own), 0o755); err != nil {
		t.Fatal(err)
	}

	moved := stamp(root)

	time.Sleep(10 * time.Millisecond)
	if err := os.WriteFile(own, []byte(`{"engine": "python"}`), 0o644); err != nil {
		t.Fatal(err)
	}

	if stamp(root) == moved {
		t.Error("a rule of the project's own was written and the service reads the same stamp")
	}
}

// TestAServiceEndingWorksOutTheAdviceItHolds holds the one thing a service owes before it goes. It ends when the
// code it runs changes — the binary, the project's config, a rule of the project's own — and the moments it had
// answered but not yet judged went with it. Those moments were answered with the gates alone, as every edit is,
// so the sins in them were never told at all and nothing said so.
func TestAServiceEndingWorksOutTheAdviceItHolds(t *testing.T) {
	var worked sync.Mutex
	var advised int

	advice := startAdviser(func(asked) {
		time.Sleep(20 * time.Millisecond)

		worked.Lock()
		advised++
		worked.Unlock()
	})

	for at := 0; at < 5; at++ {
		advice.add(asked{map[string]any{"at": at}, true, at == 0})
	}

	advice.drained(10 * time.Second)

	worked.Lock()
	defer worked.Unlock()

	if advised != 5 {
		t.Errorf("a service ending worked out %d of the 5 moments it held; the rest are sins nobody is ever told about", advised)
	}
}
