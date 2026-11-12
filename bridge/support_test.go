package bridge_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
)

func TestAFileOutsideEveryProjectNeverReachesAProjectAboveItsRepository(t *testing.T) {
	above := t.TempDir()
	repository := filepath.Join(above, "shop")
	for path, contents := range map[string]string{
		"Elsewhere.csproj":      "<Project />",
		"Elsewhere/Thing.cs":    "class Thing {}",
		"shop/.git/HEAD":        "ref: refs/heads/main\n",
		"shop/scripts/Build.cs": "class Build {}",
	} {
		written := filepath.Join(above, path)
		if err := os.MkdirAll(filepath.Dir(written), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(written, []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	if projects := bridge.CSharpProjects.Around([]string{filepath.Join(repository, "scripts", "Build.cs")}); len(projects) != 0 {
		t.Errorf("a file of %s is owned by %v, a project outside its repository", repository, projects)
	}
}
