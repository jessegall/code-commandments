package hooks

import (
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
)

func TestLanesSaysWhichTreesAreWarmAndHowFarARunningReadHasGot(t *testing.T) {
	root, _ := workerProject(t)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	settings, _ := config.Load(root)
	codebase, err := scan.Walk([]string{filepath.Join(root, "src")}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	read := &treeRead{project: project, branch: "feature/other", began: time.Now()}
	read.reached(80, 200)
	reading.Store(project+"\x00feature/other", read)
	t.Cleanup(func() { reading.Delete(project + "\x00feature/other") })

	said := lanesHeld("")

	for _, want := range []string{branch + " | warm | 2 file(s)", "held since", "feature/other | warming 40%", "80 of 200 file(s) read"} {
		if !strings.Contains(said, want) {
			t.Errorf("lanes does not say %q, so nobody can tell a warm tree from one still being read:\n%s", want, said)
		}
	}
}
