package sync

import (
	"bytes"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
)

// fakeDocker puts a docker on the PATH that logs every call and answers `image inspect` and `pull` as told, and
// answers where its log is.
func fakeDocker(t *testing.T, installed, pulls bool) string {
	t.Helper()
	bin := t.TempDir()
	log := filepath.Join(bin, "calls")
	answer := map[bool]string{true: "0", false: "1"}
	script := "#!/bin/sh\necho \"$*\" >> " + log + "\ncase \"$1\" in\n  image) exit " + answer[installed] + ";;\n  pull) exit " + answer[pulls] + ";;\nesac\n"
	if err := os.WriteFile(filepath.Join(bin, "docker"), []byte(script), 0o755); err != nil {
		t.Fatal(err)
	}
	t.Setenv("PATH", bin)

	return log
}

func project(t *testing.T, files ...string) string {
	t.Helper()
	root := t.TempDir()
	for _, file := range files {
		if err := os.MkdirAll(filepath.Dir(filepath.Join(root, file)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(root, file), []byte("class A {}\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

func calls(log string) string {
	raw, _ := os.ReadFile(log)

	return string(raw)
}

func TestSyncPullsTheCSharpImageForAProjectThatWritesCSharp(t *testing.T) {
	log := fakeDocker(t, false, true)
	var out bytes.Buffer
	pullRoslyn(project(t, "Shop/Cart.cs"), config.Config{}, cli.Console{Out: &out, Err: &out})
	if !strings.Contains(calls(log), "pull --quiet "+bridge.RoslynImage()) || !strings.Contains(out.String(), "pulled the C# bridge image") {
		t.Errorf("docker was called %q and sync said %q", calls(log), out.String())
	}
}

func TestSyncLeavesDockerAloneForAProjectWithoutCSharpOrWithTheImage(t *testing.T) {
	log := fakeDocker(t, true, true)
	pullRoslyn(project(t, "src/Cart.php"), config.Config{}, cli.Console{Out: &bytes.Buffer{}, Err: &bytes.Buffer{}})
	if calls(log) != "" {
		t.Errorf("a project with no C# called docker: %q", calls(log))
	}
	pullRoslyn(project(t, "Shop/Cart.cs"), config.Config{}, cli.Console{Out: &bytes.Buffer{}, Err: &bytes.Buffer{}})
	if strings.Contains(calls(log), "pull") {
		t.Errorf("an image already held was pulled again: %q", calls(log))
	}
}

func TestAFailedPullSaysWhichImageCSharpNeeds(t *testing.T) {
	fakeDocker(t, false, false)
	var out bytes.Buffer
	pullRoslyn(project(t, "Shop/Cart.cs"), config.Config{}, cli.Console{Out: &out, Err: &out})
	if !strings.Contains(out.String(), "docker pull "+bridge.RoslynImage()) {
		t.Errorf("sync said %q", out.String())
	}
}

func TestSyncRemovesTheBridgeThePhpToolBuiltOnTheHost(t *testing.T) {
	t.Setenv("HOME", t.TempDir())
	builds := hostRoslynBuilds()
	if err := os.MkdirAll(filepath.Join(builds, "v1"), 0o755); err != nil {
		t.Fatal(err)
	}
	var out bytes.Buffer
	removeHostRoslynBuilds(cli.Console{Out: &out, Err: &out})
	if _, err := os.Stat(builds); err == nil || !strings.Contains(out.String(), "removed the C# bridge") {
		t.Errorf("the host builds remain (%v) and sync said %q", err, out.String())
	}
	out.Reset()
	removeHostRoslynBuilds(cli.Console{Out: &out, Err: &out})
	if out.Len() > 0 {
		t.Errorf("with nothing left to remove sync still said %q", out.String())
	}
}
