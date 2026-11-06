package warm_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/warm"
)

func TestARunFindsTheServiceThatSaysItServesItsFolder(t *testing.T) {
	t.Setenv("XDG_CACHE_HOME", t.TempDir())

	project, elsewhere := folder(t), folder(t)

	if _, said := warm.ServingFor(project); said {
		t.Error("a run found a service although none has said it serves anything")
	}

	forget, err := warm.Register(project)
	if err != nil {
		t.Fatal(err)
	}

	serving, said := warm.ServingFor(project)
	if !said || serving.Project != project || serving.Socket != warm.Socket(project) {
		t.Errorf("the project's own folder finds %+v, said %v", serving, said)
	}

	inside := filepath.Join(project, ".journal", "worktrees", "worker-1")
	if err := os.MkdirAll(inside, 0o755); err != nil {
		t.Fatal(err)
	}

	if serving, said := warm.ServingFor(inside); !said || serving.Project != project {
		t.Errorf("a folder inside the project finds %+v, said %v", serving, said)
	}

	if serving, said := warm.ServingFor(elsewhere); said {
		t.Errorf("a folder in no registered project finds %+v", serving)
	}

	forget()

	if _, said := warm.ServingFor(project); said {
		t.Error("a service that has packed away is still found")
	}
}

func TestTheInnermostProjectServingAFolderIsTheOne(t *testing.T) {
	t.Setenv("XDG_CACHE_HOME", t.TempDir())

	outer := folder(t)
	inner := filepath.Join(outer, "nested", "project")
	if err := os.MkdirAll(inner, 0o755); err != nil {
		t.Fatal(err)
	}

	if _, err := warm.Register(outer); err != nil {
		t.Fatal(err)
	}
	if _, err := warm.Register(inner); err != nil {
		t.Fatal(err)
	}

	if serving, said := warm.ServingFor(inner); !said || serving.Project != inner {
		t.Errorf("a folder two projects hold finds %+v, want the innermost", serving)
	}

	if serving, said := warm.ServingFor(outer); !said || serving.Project != outer {
		t.Errorf("the outer project's own folder finds %+v", serving)
	}
}

func folder(t *testing.T) string {
	t.Helper()

	resolved, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	return resolved
}
