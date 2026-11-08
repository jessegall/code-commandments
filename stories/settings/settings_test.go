//go:build story

package settings

import (
	"testing"

	"github.com/jessegall/code-commandments/story"
)

var inVendor = story.Sample{Under: "vendor", Label: "an else after a raise in a folder the config excludes", Text: story.ElseAfterRaise.Text}

func TestThePluginsOwnSwitchesAreNotProjectConfig(t *testing.T) {
	s := story.Begin(t)

	schema := s.Asks("rules", "schema")

	s.Never(schema, "judge_workers", "judge_merges", "judge_nodes_held", "judge_files_at_once")
}

func TestAWorkerRunsTheCommandmentsCliWhileTheSettingAllowsIt(t *testing.T) {
	s := story.Begin(t)

	ran := s.Worker("Grace").Runs("bin/commandments info redundant-else")

	s.Never(ran, "workers_cli")
}

func TestAWorkerIsRefusedTheCommandmentsCliWhenTheSettingDeniesIt(t *testing.T) {
	s := story.Begin(t)
	s.SwitchesThePluginSetting("workers_cli", "false", "true")

	ran := s.Worker("Grace").Runs("bin/commandments info redundant-else")

	s.Refuses(ran)
	s.Says(ran, "workers_cli")
}

func TestTheMainAgentRunsTheCommandmentsCliWhenTheSettingDeniesWorkers(t *testing.T) {
	s := story.Begin(t)
	s.SwitchesThePluginSetting("workers_cli", "false", "true")

	ran := s.Agent("Ada").Runs("bin/commandments info redundant-else")

	s.Never(ran, "workers_cli")
}

func TestAFolderInTheProjectsExclusionsIsNotReadAtAll(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	s.Writes(inVendor, "old.py")

	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Never(judged, "RedundantElseDetector")
}
