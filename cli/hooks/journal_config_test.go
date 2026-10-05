package hooks

import (
	"bytes"
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
