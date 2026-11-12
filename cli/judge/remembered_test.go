package judge

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cache"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/atomic"
)

func TestARunRemembersInThePluginFolderWhenThereIsOne(t *testing.T) {
	folder := t.TempDir()
	t.Setenv(pluginData, folder)

	remembered, keeps := RememberedFor("/a/project", func(...string) {})
	if !keeps || filepath.Dir(remembered.Memories.Path) != folder || filepath.Dir(remembered.Weighings.Path) != folder {
		t.Errorf("the memories are kept at %q and %q, and the plugin's folder is %q", remembered.Memories.Path, remembered.Weighings.Path, folder)
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

func TestAJudgeStopsAtOnceWhileAnotherRunReadsTheProject(t *testing.T) {
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

	release, taken := atomic.ClaimAt(filepath.Join(folder, readingFile), readingClaimedFor).Take()
	if !taken {
		t.Fatal("the test could not claim the project for the run it stands in for")
	}

	var out, errs strings.Builder
	started := time.Now()
	code, _ := (Command{}).Run(cli.InputOf("judge", "--no-checklist"), cli.Console{Out: &out, Err: &errs})

	if code != readingElsewhere || !strings.Contains(errs.String(), "another run is reading") {
		t.Errorf("a judge while another run reads the project ended %d saying %q", code, errs.String())
	}
	if waited := time.Since(started); waited > 5*time.Second {
		t.Errorf("the judge waited %v for the other run instead of stopping at once", waited)
	}

	release()

	if code, _ := (Command{}).Run(cli.InputOf("judge", "--no-checklist"), cli.Console{Out: &out, Err: &errs}); code == readingElsewhere {
		t.Error("a judge after the other run ended still found the project being read")
	}
}
