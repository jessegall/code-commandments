package hooks

import (
	"bytes"
	"encoding/json"
	"github.com/jessegall/code-commandments/cli/agents"
	"os"
	"path/filepath"
	"slices"
	"strings"
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

// TestAnUpgradeRefreshesWhatTheProjectIsTold holds the reason journal-config writes the briefing at all: a project
// the journal drives never runs composer, so until now a fix to what the tool injects reached a composer project
// and no other. The upgrade writes both instruction files, names the binary the plugin really installed rather than
// a bare command nothing put on the PATH, and a second run leaves every byte as it was.
func TestAnUpgradeRefreshesWhatTheProjectIsTold(t *testing.T) {
	project := t.TempDir()
	plugin := filepath.Join(project, ".journal", "plugins", "code-commandments")
	if err := os.MkdirAll(filepath.Join(plugin, "bin"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(filepath.Join(plugin, ".journal-plugin"), 0o755); err != nil {
		t.Fatal(err)
	}
	for path, body := range map[string]string{
		filepath.Join(plugin, ".journal-plugin", "plugin.json"): "{}",
		filepath.Join(plugin, "bin", "commandments-release"):    "#!/bin/sh\n",
	} {
		if err := os.WriteFile(path, []byte(body), 0o755); err != nil {
			t.Fatal(err)
		}
	}

	t.Setenv(journalProject, project)
	t.Setenv(journalPluginDir, plugin)
	t.Setenv(journalSettings, `{}`)

	var out, errs bytes.Buffer
	if _, err := (JournalConfig{}).Run(&cli.Input{}, cli.Console{Out: &out, Err: &errs}); err != nil {
		t.Fatalf("%v: %s", err, errs.String())
	}

	canon := filepath.Join(project, "AGENTS.md")
	told, err := os.ReadFile(canon)
	if err != nil {
		t.Fatalf("the upgrade wrote the project no briefing: %v: %s", err, errs.String())
	}
	if !bytes.Contains(told, []byte(agents.BriefingBlock)) {
		t.Errorf("AGENTS.md holds no %q block:\n%s", agents.BriefingBlock, told)
	}
	if want := ".journal/plugins/code-commandments/bin/commandments-release"; !bytes.Contains(told, []byte(want)) {
		t.Errorf("the briefing names no %s, so it tells the agent to run a file that is not there:\n%s", want, told)
	}

	if _, err := os.Stat(filepath.Join(project, "CLAUDE.md")); err != nil {
		t.Errorf("the upgrade wrote Claude's own file nothing: %v", err)
	}

	out.Reset()
	if _, err := (JournalConfig{}).Run(&cli.Input{}, cli.Console{Out: &out, Err: &errs}); err != nil {
		t.Fatalf("the second run failed: %v: %s", err, errs.String())
	}

	again, _ := os.ReadFile(canon)
	if !bytes.Equal(told, again) {
		t.Error("a second upgrade rewrote AGENTS.md, so every upgrade dirties a tracked file")
	}
	if said := strings.TrimSpace(out.String()); said != "{}" {
		t.Errorf("a second upgrade with nothing to change said %q", said)
	}
}

// TestAFileTheUpgradeCannotTrustIsSaidAndNotWritten holds an upgrade to doing no harm when a project's instruction
// file cannot be read safely: a marker a reader once typed by hand must not fail the upgrade, undo the switches it
// has already applied, or be written past in silence. The file is left exactly as it was and the refusal is said.
func TestAFileTheUpgradeCannotTrustIsSaidAndNotWritten(t *testing.T) {
	project := t.TempDir()
	ambiguous := "<!-- BEGIN: " + agents.BriefingBlock + " -->\n<!-- BEGIN: " + agents.BriefingBlock + " -->\n"

	if err := os.WriteFile(filepath.Join(project, "AGENTS.md"), []byte(ambiguous), 0o644); err != nil {
		t.Fatal(err)
	}

	t.Setenv(journalProject, project)
	t.Setenv(journalPluginDir, t.TempDir())
	t.Setenv(journalSettings, `{"judge_parallel": "4"}`)

	var out, errs bytes.Buffer
	code, err := (JournalConfig{}).Run(&cli.Input{}, cli.Console{Out: &out, Err: &errs})

	if err != nil || code != 0 {
		t.Fatalf("the upgrade failed over one unreadable file: %d %v: %s", code, err, errs.String())
	}

	if told, _ := os.ReadFile(filepath.Join(project, "AGENTS.md")); string(told) != ambiguous {
		t.Errorf("the refused file was written anyway:\n%s", told)
	}

	if !strings.Contains(out.String(), "the briefing was left alone") {
		t.Errorf("the upgrade said nothing about the file it could not write: %s", out.String())
	}

	judged, err := config.Load(project)
	if err != nil {
		t.Fatal(err)
	}
	if judged.Parallel != 4 {
		t.Errorf("the refusal cost the project the switches the upgrade had already applied: %d", judged.Parallel)
	}
}
