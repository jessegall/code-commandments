package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"sync"
	"testing"
	"time"
)

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

func TestAnEditsAdviceIsWorkedOutFirst(t *testing.T) {
	var worked sync.Mutex
	var order []string

	advice := &adviser{wake: make(chan struct{}, 1), advise: func(moment asked) {
		worked.Lock()
		order = append(order, asText(moment.given["named"]))
		worked.Unlock()
	}}

	advice.add(asked{map[string]any{"named": "a heartbeat"}, true, false})
	advice.add(asked{map[string]any{"named": "a stop"}, true, false})
	advice.add(asked{map[string]any{"named": "an edit"}, true, true})
	advice.add(asked{map[string]any{"named": "another edit"}, true, true})

	go advice.work()
	advice.drained(10 * time.Second)

	worked.Lock()
	defer worked.Unlock()

	if len(order) != 3 {
		t.Fatalf("the agent's 4 moments were worked out in %d passes, want 3: %v", len(order), order)
	}

	if order[0] != "an edit" {
		t.Errorf("the agent's moments were worked out as %v; its edits come first, since it is waiting on them", order)
	}
}

func TestOneAgentsMomentsNeverWaitBehindAnothers(t *testing.T) {
	slow := make(chan struct{})
	quick := make(chan struct{})

	advice := startAdvisers(func(moment asked) {
		switch MomentOf(moment.given).Agent {
		case 1:
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

func TestAnAgentsEditsAreWorkedOutBeforeItsStop(t *testing.T) {
	var worked sync.Mutex
	var order []string

	advice := startAdvisers(func(moment asked) {
		worked.Lock()
		order = append(order, asText(moment.given["named"]))
		worked.Unlock()
	})

	advice.add(asked{map[string]any{"named": "its stop", "agent": map[string]any{"id": 7}}, true, false})
	advice.add(asked{map[string]any{"named": "its edit", "agent": map[string]any{"id": 7}}, true, true})

	advice.drained(10 * time.Second)

	worked.Lock()
	defer worked.Unlock()

	if len(order) != 2 {
		t.Fatalf("the agent's 2 moments were worked out as %v", order)
	}

	if order[0] != "its edit" {
		t.Errorf("the agent's moments were worked out as %v; its own edit comes before its own stop, since what "+
			"is unjudged when it stops reaches nobody", order)
	}
}
