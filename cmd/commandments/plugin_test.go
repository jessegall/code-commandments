package main

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

// pluginFolder lays out what the journal clones for the plugin: its fetch script, and the bin folder it fetches into.
func pluginFolder(t *testing.T) (root string) {
	t.Helper()
	for _, tool := range []string{"sh", "curl"} {
		if _, err := exec.LookPath(tool); err != nil {
			t.Skipf("%s is not on PATH", tool)
		}
	}
	root = t.TempDir()
	copyFile(t, filepath.Join("..", "..", ".journal-plugin", "fetch"), filepath.Join(root, ".journal-plugin", "fetch"))
	if err := os.MkdirAll(filepath.Join(root, "bin"), 0o755); err != nil {
		t.Fatal(err)
	}

	return root
}

// fetchRun runs the plugin's fetch script with the version pinned, fetching releases from base.
func fetchRun(t *testing.T, root, version, base string) (string, error) {
	t.Helper()
	command := exec.Command("sh", filepath.Join(root, ".journal-plugin", "fetch"))
	command.Env = append(os.Environ(), "COMMANDMENTS_RELEASE="+version, "COMMANDMENTS_RELEASES="+base)
	out, err := command.CombinedOutput()

	return string(out), err
}

// fetched is every file the fetch left in the plugin's bin folder.
func fetched(t *testing.T, root string) []string {
	t.Helper()
	files, _ := filepath.Glob(filepath.Join(root, "bin", "*"))

	return files
}

// TestThePluginFetchesThePinnedReleaseCheckedAgainstItsSums holds the plugin's setup to its one job: the pinned
// release's binary for this platform, checked against SHA256SUMS, written executable where every command runs it.
func TestThePluginFetchesThePinnedReleaseCheckedAgainstItsSums(t *testing.T) {
	root := pluginFolder(t)
	server := release(t, "v9.9.9", fakeBinary, sumOf(fakeBinary))
	if out, err := fetchRun(t, root, "v9.9.9", server.URL); err != nil {
		t.Fatalf("the fetch said %q (%v)", out, err)
	}
	binary := filepath.Join(root, "bin", "commandments-release")
	if out, err := exec.Command(binary, "judge", "src").Output(); err != nil || strings.TrimSpace(string(out)) != "ran judge src" {
		t.Errorf("the fetched binary said %q (%v)", out, err)
	}
}

func TestThePluginRefusesABinaryWhoseSumDiffers(t *testing.T) {
	root := pluginFolder(t)
	server := release(t, "v9.9.9", fakeBinary, sumOf("another binary"))
	out, err := fetchRun(t, root, "v9.9.9", server.URL)
	if err == nil || !strings.Contains(out, "does not match its SHA256SUMS") {
		t.Errorf("the fetch said %q (%v)", out, err)
	}
	if files := fetched(t, root); len(files) > 0 {
		t.Errorf("the refused binary was kept: %v", files)
	}
}

func TestThePluginRefusesAReleaseItCannotFetch(t *testing.T) {
	root := pluginFolder(t)
	server := release(t, "v9.9.9", fakeBinary, sumOf(fakeBinary))
	out, err := fetchRun(t, root, "v9.9.8", server.URL)
	if err == nil || !strings.Contains(out, "could not fetch") {
		t.Errorf("the fetch said %q (%v)", out, err)
	}
	if files := fetched(t, root); len(files) > 0 {
		t.Errorf("a binary was written: %v", files)
	}
}

func TestThePluginRefusesAPinThatIsNoRelease(t *testing.T) {
	root := pluginFolder(t)
	out, err := fetchRun(t, root, "main", "http://127.0.0.1:1")
	if err == nil || !strings.Contains(out, "names no release (main)") {
		t.Errorf("the fetch said %q (%v)", out, err)
	}
}
