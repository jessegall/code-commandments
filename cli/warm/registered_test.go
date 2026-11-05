package warm_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/warm"
)

// TestARunFindsTheServiceThatSaysItServesItsFolder holds the way out of one circle: a socket is named from the
// project's path, so a run that does not know the project cannot name the socket. The service writes down what
// it serves, and a run takes the record whose project holds the folder it stands in.
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

	// A worker stands in a worktree, which lies inside the project.
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

// TestTheInnermostProjectServingAFolderIsTheOne holds which service answers where two hold the same folder: a
// project inside a project, which a checkout under another's folder is.
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

// folder is a folder of its own, with its links resolved as the records resolve them.
func folder(t *testing.T) string {
	t.Helper()

	resolved, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	return resolved
}
