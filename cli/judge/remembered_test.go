package judge

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/ledger"
)

func TestAPluginRunAndARunByHandRememberInOnePlace(t *testing.T) {
	t.Setenv("XDG_CACHE_HOME", t.TempDir())

	t.Setenv(pluginData, t.TempDir())
	plugin, _ := RememberedFor("/a/project", func(...string) {})

	t.Setenv(pluginData, "")
	byHand, _ := RememberedFor("/a/project", func(...string) {})

	if plugin.Memories.Path != byHand.Memories.Path {
		t.Errorf("the plugin's run remembers at %q and a run by hand at %q, so neither sees the other reading the project", plugin.Memories.Path, byHand.Memories.Path)
	}
}

func TestAPlainRunRemembersUnderTheUsersCacheOnePlacePerProject(t *testing.T) {
	cache := t.TempDir()
	t.Setenv(pluginData, "")
	t.Setenv("XDG_CACHE_HOME", cache)

	one, keeps := RememberedFor("/a/project", func(...string) {})
	other, _ := RememberedFor("/another/project", func(...string) {})
	again, _ := RememberedFor("/a/project", func(...string) {})

	if !keeps || !strings.HasPrefix(one.Memories.Path, cache) {
		t.Fatalf("a plain run keeps its memories at %q, outside the cache %q", one.Memories.Path, cache)
	}

	if one.Memories.Path == other.Memories.Path {
		t.Errorf("two projects share the memory file %q", one.Memories.Path)
	}

	if one.Memories.Path != again.Memories.Path {
		t.Errorf("one project remembers in %q and then in %q", one.Memories.Path, again.Memories.Path)
	}
}

func TestARunHandsOnTheHoursItsProjectKeepsACache(t *testing.T) {
	project := t.TempDir()
	t.Setenv(cache.Variable, "")
	t.Setenv(pluginData, t.TempDir())

	if err := os.MkdirAll(filepath.Join(project, ".commandments"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(project, ".commandments", "config.json"), []byte(`{"cache_hours": 3}`), 0o644); err != nil {
		t.Fatal(err)
	}

	RememberedFor(project, func(...string) {})

	if kept := cache.KeptFor(); kept != 3*time.Hour {
		t.Errorf("a project that keeps its caches 3 hours runs with %v", kept)
	}
}

func TestAJudgeWaitsWhileAnotherRunReadsTheProjectThenRuns(t *testing.T) {
	root := t.TempDir()
	folder := t.TempDir()
	t.Setenv(pluginData, folder)

	if err := os.MkdirAll(filepath.Join(root, "src"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "src", "order.py"), []byte("class Order:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if out, err := exec.Command("git", "-C", root, "init", "-q").CombinedOutput(); err != nil {
		t.Fatalf("%s", out)
	}
	t.Chdir(root)

	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	remembered, _ := RememberedFor(root, func(...string) {})
	release, taken := remembered.ReadAlone()
	if !taken {
		t.Fatal("the test could not claim the project for the run it stands in for")
	}

	var out, errs strings.Builder
	ended := make(chan int, 1)
	go func() {
		code, _ := (Command{}).Run(cli.InputOf("judge", "--no-checklist"), cli.Console{Out: &out, Err: &errs})
		ended <- code
	}()

	select {
	case code := <-ended:
		t.Fatalf("a judge ran beside another run of the project, ending %d", code)
	case <-time.After(300 * time.Millisecond):
	}

	release()

	select {
	case code := <-ended:
		if code == readingElsewhere {
			t.Error("a judge after the other run ended still found the project being read")
		}
	case <-time.After(40 * time.Second):
		t.Fatal("the judge never got its turn after the other run ended")
	}
}

func TestAJournalProjectAndItsWorktreesRememberAtTheJournal(t *testing.T) {
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	t.Setenv(pluginData, "")
	t.Setenv(cache.Folder, "")

	project, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	manifest := filepath.Join(project, ".journal", "plugins", "code-commandments", ".journal-plugin", "plugin.json")
	if err := os.MkdirAll(filepath.Dir(manifest), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(manifest, []byte("{}"), 0o644); err != nil {
		t.Fatal(err)
	}

	worktree := filepath.Join(project, ".journal", "worktrees", "worker-1")
	for _, line := range [][]string{
		{"init", "-q", "-b", "main"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-q", "--allow-empty", "-m", "the project"},
		{"worktree", "add", "-q", "-b", "a-worker", worktree},
	} {
		if out, err := exec.Command("git", append([]string{"-C", project}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	atTheRoot, _ := RememberedFor(project, func(...string) {})
	inTheWorktree, _ := RememberedFor(worktree, func(...string) {})

	if atTheRoot.Memories.Path != inTheWorktree.Memories.Path {
		t.Errorf("the project remembers at %q and its worktree at %q", atTheRoot.Memories.Path, inTheWorktree.Memories.Path)
	}

	if !strings.HasPrefix(atTheRoot.Memories.Path, filepath.Join(project, ".journal", "plugin-data")) {
		t.Errorf("a journal project remembers at %q, not at its journal", atTheRoot.Memories.Path)
	}
}

func TestASecondJudgeWaitsItsTurnInsteadOfRunningBesideTheFirst(t *testing.T) {
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	t.Setenv(pluginData, "")

	waited := make(chan bool, 1)
	first, _ := RememberedFor("/a/project", func(...string) {})
	second, _ := RememberedFor("/another/project", func(...string) { waited <- true })

	release, alone := first.ReadAlone()
	if !alone {
		t.Fatal("the first judge did not get its turn")
	}

	ran := make(chan bool, 1)
	go func() {
		done, alone := second.ReadAlone()
		ran <- alone
		done()
	}()

	select {
	case <-waited:
	case <-time.After(5 * time.Second):
		t.Fatal("the second judge was never told it waits")
	}

	select {
	case <-ran:
		t.Fatal("the second judge ran beside the first")
	case <-time.After(200 * time.Millisecond):
	}

	release()

	select {
	case alone := <-ran:
		if !alone {
			t.Error("the second judge stopped instead of taking its turn")
		}
	case <-time.After(5 * time.Second):
		t.Fatal("the second judge never got its turn after the first ended")
	}
}

func TestAJournalProjectAndItsWorktreesKeepTheirParsedTreesAtTheJournal(t *testing.T) {
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	t.Setenv(pluginData, "")
	t.Setenv(cache.Folder, "")

	project, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	manifest := filepath.Join(project, ".journal", "plugins", "code-commandments", ".journal-plugin", "plugin.json")
	if err := os.MkdirAll(filepath.Dir(manifest), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(manifest, []byte("{}"), 0o644); err != nil {
		t.Fatal(err)
	}

	worktree := filepath.Join(project, ".journal", "worktrees", "worker-1")
	for _, line := range [][]string{
		{"init", "-q", "-b", "main"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-q", "--allow-empty", "-m", "the project"},
		{"worktree", "add", "-q", "-b", "a-worker", worktree},
	} {
		if out, err := exec.Command("git", append([]string{"-C", project}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	journal := filepath.Join(project, ".journal", "plugin-data", "code-commandments")

	for _, root := range []string{project, worktree} {
		RememberedFor(root, func(...string) {})

		trees, err := bundle.TreesFolder("python")
		if err != nil {
			t.Fatal(err)
		}

		if !strings.HasPrefix(trees, journal+"/") {
			t.Errorf("a judge in %s keeps its parsed trees at %s, not at the project's journal", root, trees)
		}
	}
}

func TestAJudgeOfAJournalProjectCountsTheFilesItJudgedAndRemembered(t *testing.T) {
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	t.Setenv(pluginData, "")
	t.Setenv(cache.Folder, "")

	project := sinfulProject(t)
	manifest := filepath.Join(project, ".journal", "plugins", "code-commandments", ".journal-plugin", "plugin.json")
	if err := os.MkdirAll(filepath.Dir(manifest), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(manifest, []byte("{}"), 0o644); err != nil {
		t.Fatal(err)
	}
	if out, err := exec.Command("git", "-C", project, "init", "-q").CombinedOutput(); err != nil {
		t.Fatalf("%s", out)
	}

	costs := ledger.Of(project)

	judgedIn(t, project, "--no-checklist", "--parallel=1")
	judged := costs.Count(ledger.DetectorFilesJudged)
	if judged == 0 {
		t.Fatalf("a first judge counted no file judged")
	}

	judgedIn(t, project, "--no-checklist", "--parallel=1")
	if again := costs.Count(ledger.DetectorFilesJudged); again != judged {
		t.Errorf("a second judge of the same files counted %d more judged", again-judged)
	}
	if costs.Count(ledger.DetectorFilesRemembered) == 0 {
		t.Error("a second judge of the same files counted none remembered")
	}
}
