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

// TestAnEditsAdviceIsWorkedOutFirst holds the order of the queue. One moment is judged at a time, which is what
// keeps two checks of one file from running at once, so whatever is at its head decides when every edit behind it
// is answered. A session raises a heartbeat every minute for every open agent and one at every stop, and each of
// those settles every sin the session has announced; nobody is waiting on any of them, while an agent is waiting
// to hear about the file it just wrote.
func TestAnEditsAdviceIsWorkedOutFirst(t *testing.T) {
	var worked sync.Mutex
	var order []string

	advice := &adviser{wake: make(chan struct{}, 1), advise: func(moment asked) {
		worked.Lock()
		order = append(order, asText(moment.given["named"]))
		worked.Unlock()
	}}

	// Added before the worker starts, so all four are waiting when it picks the first.
	advice.add(asked{map[string]any{"named": "a heartbeat"}, true, false})
	advice.add(asked{map[string]any{"named": "a stop"}, true, false})
	advice.add(asked{map[string]any{"named": "an edit"}, true, true})
	advice.add(asked{map[string]any{"named": "another edit"}, true, true})

	go advice.work()
	advice.drained(10 * time.Second)

	worked.Lock()
	defer worked.Unlock()

	if len(order) != 4 {
		t.Fatalf("the queue worked out %d of the 4 moments it held: %v", len(order), order)
	}

	if order[0] != "an edit" || order[1] != "another edit" {
		t.Errorf("the queue worked out %v; the edits come first, since an agent is waiting on them", order)
	}
}

// TestOneAgentsMomentsNeverWaitBehindAnothers holds the one thing that made a worker hear about its sin four
// minutes late. There was ONE queue for the whole project, so a moment waited behind every moment before it,
// whoever raised it: measured on a consumer with three workers, a fourth, the main agent and a 233-test run
// going at once, judged_total 27955 ms for 143 ms of its own work, and one worker's answer arrived 3 min 51 s
// after its write, by which time that worker had ended and its sin was never told at all.
//
// An agent's own moments are still worked out in order, since two checks of one file must never run at once and
// a file belongs to the checkout of one agent. What goes is the waiting it never owed.
func TestOneAgentsMomentsNeverWaitBehindAnothers(t *testing.T) {
	slow := make(chan struct{})
	quick := make(chan struct{})

	advice := startAdvisers(func(moment asked) {
		switch MomentOf(moment.given).Agent {
		case 1:
			// The first agent's moment is still being worked out when the test ends, as a judging of a large
			// tree is while another agent writes.
			<-slow
		case 2:
			close(quick)
		}
	})

	advice.add(asked{map[string]any{"agent": map[string]any{"id": 1}}, true, true})
	advice.add(asked{map[string]any{"agent": map[string]any{"id": 2}}, true, true})

	select {
	case <-quick:
	case <-time.After(10 * time.Second):
		t.Error("the second agent's moment waited on the first agent's, which is how a worker heard about its " +
			"sin four minutes after it wrote it")
	}

	close(slow)
	advice.drained(10 * time.Second)
}

// TestAnAgentsOwnMomentsAreWorkedOutInOrder holds the other half: one agent's moments are never worked out at
// the same time as one another, since two checks of one file must never run at once.
func TestAnAgentsOwnMomentsAreWorkedOutInOrder(t *testing.T) {
	var working sync.Mutex
	var at, most int

	advice := startAdvisers(func(asked) {
		working.Lock()
		at++
		most = max(most, at)
		working.Unlock()

		time.Sleep(20 * time.Millisecond)

		working.Lock()
		at--
		working.Unlock()
	})

	for moment := 0; moment < 6; moment++ {
		advice.add(asked{map[string]any{"agent": map[string]any{"id": 7}}, true, true})
	}

	advice.drained(10 * time.Second)

	working.Lock()
	defer working.Unlock()

	if most != 1 {
		t.Errorf("one agent had %d of its own moments worked out at once; two checks of one file must never run "+
			"together", most)
	}
}
