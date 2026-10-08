package hooks

import (
	"bytes"
	"encoding/json"
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
)

// TestThePluginsSettingSaysHowManyRulesJudgeRunsAtOnce holds journal-config to the plugin's judge_parallel: the
// number is written into the project's config, where judge takes it when no --parallel says otherwise.
func TestThePluginsSettingSaysHowManyRulesJudgeRunsAtOnce(t *testing.T) {
	project := t.TempDir()
	t.Setenv(journalProject, project)
	t.Setenv(journalPluginDir, t.TempDir())
	t.Setenv(journalSettings, `{"judge_parallel": "4"}`)

	var out, errs bytes.Buffer
	if _, err := (JournalConfig{}).Run(&cli.Input{}, cli.Console{Out: &out, Err: &errs}); err != nil {
		t.Fatalf("%v: %s", err, errs.String())
	}

	judged, err := config.Load(project)
	if err != nil {
		t.Fatal(err)
	}
	if judged.Parallel != 4 {
		t.Errorf("the config runs %d detectors at once: %s", judged.Parallel, out.String())
	}
}

// TestAnUpgradeKeepsTheFoldersTheProjectChose holds journal-scan to the project's own config: the folders it answers
// are the ones the config already judges and leaves out, so journal-config applying them back keeps every one.
func TestAnUpgradeKeepsTheFoldersTheProjectChose(t *testing.T) {
	project := t.TempDir()
	t.Setenv(journalProject, project)
	t.Setenv(journalPluginDir, t.TempDir())
	for _, folder := range []string{"src", ".venv", "src/web/dist"} {
		if err := os.MkdirAll(filepath.Join(project, folder), 0o755); err != nil {
			t.Fatal(err)
		}
	}
	written := `{"paths": ["src"], "exclude": ["src/web/dist", "src/web/dist-demo", ".claude/worktrees", ".venv"]}`
	if err := os.MkdirAll(filepath.Join(project, ".commandments"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(project, ".commandments", "config.json"), []byte(written), 0o644); err != nil {
		t.Fatal(err)
	}

	var scanned, errs bytes.Buffer
	if _, err := (JournalScan{}).Run(&cli.Input{}, cli.Console{Out: &scanned, Err: &errs}); err != nil {
		t.Fatalf("%v: %s", err, errs.String())
	}
	var answer struct {
		Settings map[string]any `json:"settings"`
	}
	if err := json.Unmarshal(scanned.Bytes(), &answer); err != nil {
		t.Fatalf("%v: %s", err, scanned.String())
	}
	chosen, _ := json.Marshal(answer.Settings)
	t.Setenv(journalSettings, string(chosen))
	if _, err := (JournalConfig{}).Run(&cli.Input{}, cli.Console{Out: &bytes.Buffer{}, Err: &errs}); err != nil {
		t.Fatalf("%v: %s", err, errs.String())
	}

	kept, err := config.Load(project)
	if err != nil {
		t.Fatal(err)
	}
	if want := []string{"src/web/dist", "src/web/dist-demo", ".claude/worktrees", ".venv"}; !slices.Equal(kept.Excluded, want) {
		t.Errorf("the upgrade leaves out %v", kept.Excluded)
	}
}

// TestThePluginsSetupLeavesTheProjectsFoldersAlone holds the setup step to the switches: on an upgrade the journal
// runs it with the folders it stored at install, which may be older than the config, so the folders the project
// judges and leaves out are kept until journal-scan has read them back.
func TestThePluginsSetupLeavesTheProjectsFoldersAlone(t *testing.T) {
	project := t.TempDir()
	t.Setenv(journalProject, project)
	t.Setenv(journalPluginDir, t.TempDir())
	for _, folder := range []string{"platform/app", "mobile/src"} {
		if err := os.MkdirAll(filepath.Join(project, folder), 0o755); err != nil {
			t.Fatal(err)
		}
	}
	if err := os.MkdirAll(filepath.Join(project, ".commandments"), 0o755); err != nil {
		t.Fatal(err)
	}
	written := `{"paths": ["platform/app"], "exclude": ["platform/vendor"]}`
	if err := os.WriteFile(filepath.Join(project, ".commandments", "config.json"), []byte(written), 0o644); err != nil {
		t.Fatal(err)
	}
	t.Setenv(journalSettings, `{"folders_judged": "mobile/src", "folders_skipped": "", "judge_parallel": "2"}`)

	var errs bytes.Buffer
	if _, err := (JournalConfig{}).Run(cli.InputOf("journal-config", "--keep-folders"), cli.Console{Out: &bytes.Buffer{}, Err: &errs}); err != nil {
		t.Fatalf("%v: %s", err, errs.String())
	}

	kept, err := config.Load(project)
	if err != nil {
		t.Fatal(err)
	}
	if !slices.Equal(kept.Paths, []string{"platform/app"}) || !slices.Equal(kept.Excluded, []string{"platform/vendor"}) {
		t.Errorf("the setup left paths %v and exclude %v", kept.Paths, kept.Excluded)
	}
}
