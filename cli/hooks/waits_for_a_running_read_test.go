package hooks

import (
	"bytes"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/judge"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/warm"
)

func projectHeldByNothing(t *testing.T) string {
	t.Helper()

	root := t.TempDir()

	for _, line := range [][]string{{"init", "-q", "-b", "main"}, {"config", "user.email", "t@t"}, {"config", "user.name", "t"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	if err := os.MkdirAll(filepath.Join(root, "src"), 0o755); err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(filepath.Join(root, "src", "order.py"), []byte(olderSin), 0o644); err != nil {
		t.Fatal(err)
	}

	for _, line := range [][]string{{"add", "-A"}, {"commit", "-qm", "first"}} {
		if out, err := exec.Command("git", append([]string{"-C", root}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	return root
}

func TestAJudgingIsToldTheTreeIsComingWhileAReadIsRunning(t *testing.T) {
	root := projectHeldByNothing(t)

	nowServing(t)

	named, ok := rootOf(root)
	if !ok {
		t.Fatal("the project has no branch root, so this test would prove nothing")
	}

	reading.Store(named.key(), true)
	t.Cleanup(func() { reading.Delete(named.key()) })

	began := time.Now()
	why, coming := TheTreeIsComing(root, root)

	if !coming {
		t.Errorf("with a read of the tree running, the judging is told the tree is not coming (%q). The run then "+
			"reads every file of the project itself, beside the read already running, which is the whole tree "+
			"parsed twice at once: 7246 files and seven minutes in Sir Jesse's project", why)
	}

	if waited := time.Since(began); waited > 5*time.Second {
		t.Errorf("the service held the connection for %v before saying the tree is coming. It must answer at once "+
			"and let the run ask again, or the run's own deadline expires and it reads the tree itself while the "+
			"service is still reading it", waited.Round(time.Millisecond))
	}
}

func TestAJudgingIsNotToldATreeIsComingWhenNoneIs(t *testing.T) {
	root := projectHeldByNothing(t)

	nowServing(t)

	named, ok := rootOf(root)
	if !ok {
		t.Fatal("the project has no branch root")
	}

	reading.Delete(named.key())
	t.Cleanup(func() { reading.Delete(named.key()) })

	if said := whatIsComing(named); said == noTreeHeld {
		t.Error("with no read running at all, the run is told one is being read now. It then has nothing to wait " +
			"for and the words are simply untrue")
	}

	reading.Store(named.key(), true)

	if said := whatIsComing(named); said != noTreeHeld {
		t.Errorf("with a read of the tree running, the run is told %q, so it never learns there is something "+
			"worth waiting for", said)
	}
}

func TestAJudgingWaitsForNothingThatCannotBeRead(t *testing.T) {
	root := t.TempDir()

	nowServing(t)

	if why, coming := TheTreeIsComing(root, root); coming {
		t.Errorf("a folder that is no project at all was answered %q as a tree on its way, so the run waits half "+
			"an hour for a read that will never start", why)
	}
}

func TestAJudgeRunOnAServedProjectWaitsForTheTreeRatherThanReadingTheProjectItself(t *testing.T) {
	root := projectHeldByNothing(t)

	nowServing(t)

	named, ok := rootOf(root)
	if !ok {
		t.Fatal("the project has no branch root, so this test would prove nothing")
	}

	reading.Store(named.key(), true)
	t.Cleanup(func() { reading.Delete(named.key()) })

	listener, listening := Judging(root)
	if !listening {
		t.Fatalf("the service cannot listen on %s", warm.Socket(root))
	}
	defer listener.Close()

	go serveJudging(listener, root)

	go func() {
		time.Sleep(1500 * time.Millisecond)

		settings, _ := config.Load(root)
		codebase, err := scan.Walk([]string{root}, source.Under(root, settings.Excluded)).Load()
		if err != nil || codebase == nil {
			return
		}

		kept.Hold(named.project, named.branch, []string{root}, codebase, nil)
		reading.Delete(named.key())
	}()
	t.Cleanup(func() { kept.Drop(named.project, named.branch) })

	var out, errs bytes.Buffer

	code, err := (judge.Command{In: root}).Run(cli.InputOf("judge", root, "--no-checklist", "--sin=redundant-python-else"),
		cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatalf("the run failed: %v\n%s", err, errs.String())
	}

	if strings.Contains(errs.String(), "reads the whole tree itself") {
		t.Fatalf("with the tree on its way, the run read the whole project itself beside the read already "+
			"running, which is the project parsed twice at once:\n%s", errs.String())
	}

	if !strings.Contains(errs.String(), "waiting for the tree") {
		t.Errorf("the run never said it waited for the tree, so it was not served by waiting:\n%s", errs.String())
	}

	if !strings.Contains(out.String(), "order.py") || code != 1 {
		t.Errorf("the run exits %d and does not name the sin in order.py, so it was not judged from the tree:\n%s%s",
			code, out.String(), errs.String())
	}
}
