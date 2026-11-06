package bridge

import (
	"os"
	"path/filepath"
	"testing"
)

func TestASocketIsNamedTheSameHoweverTheProjectWasReached(t *testing.T) {
	real, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	project := filepath.Join(real, "project")
	if err := os.Mkdir(project, 0o755); err != nil {
		t.Fatal(err)
	}

	linked := filepath.Join(real, "through-a-link")
	if err := os.Symlink(project, linked); err != nil {
		t.Skipf("this filesystem keeps no links: %v", err)
	}

	for _, named := range []struct {
		how  string
		path string
	}{
		{"through a link", linked},
		{"with a trailing separator", project + string(filepath.Separator)},
		{"with a step through itself", filepath.Join(project, ".")},
	} {
		if ServiceSocket("php", named.path) != ServiceSocket("php", project) {
			t.Errorf("a project named %s answers another socket, so a run never finds the bridge kept up for it", named.how)
		}
	}

	if ServiceSocket("php", project) == ServiceSocket("mypy", project) {
		t.Error("two bridges of one project share a socket")
	}
}

func TestASocketIsNamedForAProjectThatIsNotThere(t *testing.T) {
	missing := filepath.Join(t.TempDir(), "nothing", "here")

	if ServiceSocket("php", missing) != ServiceSocket("php", missing+string(filepath.Separator)) {
		t.Error("a path nothing is at answers two sockets")
	}

	if ServiceSocket("php", missing) == "" {
		t.Error("a path nothing is at answers no socket at all")
	}
}
