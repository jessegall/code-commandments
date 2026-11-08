package bridge

import (
	"os"
	"path/filepath"
	"testing"
)

func onThePath(t *testing.T, programs map[string]string) {
	t.Helper()

	folder := t.TempDir()
	for name, body := range programs {
		if err := os.WriteFile(filepath.Join(folder, name), []byte("#!/bin/sh\n"+body+"\n"), 0o755); err != nil {
			t.Fatal(err)
		}
	}

	t.Setenv("PATH", folder)
}

func TestARefusedDownloadFallsBackToTheGitHubCLI(t *testing.T) {
	t.Setenv("COMMANDMENTS_RELEASES", "")
	onThePath(t, map[string]string{"curl": "exit 56", "gh": "printf from-the-cli"})

	body, err := fetch("https://example.test/v1", "SHA256SUMS")

	if err != nil || string(body) != "from-the-cli" {
		t.Errorf("the download gave %q and %v, and the GitHub CLI had the file", body, err)
	}
}

func TestAReleaseFolderOfItsOwnNeverReachesForTheGitHubCLI(t *testing.T) {
	t.Setenv("COMMANDMENTS_RELEASES", "https://example.test")
	onThePath(t, map[string]string{"curl": "exit 56", "gh": "printf from-the-cli"})

	if body, err := fetch("https://example.test/v1", "SHA256SUMS"); err == nil {
		t.Errorf("the download gave %q from GitHub, and the releases were named elsewhere", body)
	}
}

func TestADownloadThatFailsEverywhereSaysWhatCurlSaid(t *testing.T) {
	t.Setenv("COMMANDMENTS_RELEASES", "")
	onThePath(t, map[string]string{"curl": "exit 56", "gh": "exit 1"})

	if _, err := fetch("https://example.test/v1", "SHA256SUMS"); err == nil {
		t.Error("nothing could be downloaded and no failure was given")
	}
}
