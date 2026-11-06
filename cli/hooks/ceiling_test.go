package hooks

import (
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/detectors"
)

func TestTheServiceRefusesANewLanePastItsCeilingAndSaysSo(t *testing.T) {
	root, _ := workerProject(t)
	worktree := stressWorktreeIn(root, 7)

	cutInto(t, root, worktree, "feature/ceiling")

	under := filepath.Join("src", "made.py")
	file := filepath.Join(worktree, under)
	wroteInto(t, file, olderSin)

	settings, _ := config.Load(root)

	Serves()
	t.Cleanup(func() {
		kept.Ceiling(0)
		serving.Store(false)
	})

	Warming(root, settings)
	warmed(t, root)

	if held := kept.NodesHeld(); held == 0 {
		t.Fatal("the service holds no nodes at all, so a ceiling over it would prove nothing")
	}

	kept.Ceiling(1)

	held, ceiling, past := kept.PastItsCeiling()
	if !past {
		t.Fatalf("the service holds %d nodes against a ceiling of %d and says it is not past it", held, ceiling)
	}

	_, _, why := AgainstTheProgramNarrowed(Marks(worktree), []string{file},
		func(string) []detectors.Detector { return allRules(t, root, settings) },
		func(string) git.ChangedLines { return git.Everywhere() }, worktree)

	if why != pastTheCeiling {
		t.Fatalf("a new lane past the ceiling was refused with %q", why)
	}

	if !strings.Contains(Unjudged("judged by fewer rules", string(why), ""), "as much of this project as it is allowed to") {
		t.Errorf("the refusal does not say what is wrong: %s", why)
	}

	before := kept.NodesHeld()
	AgainstTheProgramNarrowed(Marks(worktree), []string{file},
		func(string) []detectors.Detector { return allRules(t, root, settings) },
		func(string) git.ChangedLines { return git.Everywhere() }, worktree)

	if after := kept.NodesHeld(); after > before {
		t.Errorf("the service held %d nodes and grew to %d while past its ceiling", before, after)
	}
}

func warmed(t *testing.T, root string) {
	t.Helper()

	for until := time.Now().Add(time.Minute); warming(t) && time.Now().Before(until); {
		time.Sleep(20 * time.Millisecond)
	}

	if _, holds := kept.Any(root); !holds {
		t.Skip("no tree of the project was warmed, so a ceiling over it cannot be tested here")
	}
}
