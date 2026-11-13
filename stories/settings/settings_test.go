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

	s.Never(schema, "judge_workers", "judge_merges", "judge_files_at_once")
}

func TestAWorkerRunsTheCommandmentsCliWhenTheSettingAllowsIt(t *testing.T) {
	s := story.Begin(t)
	s.SwitchesThePluginSetting("workers_cli", "true", "false")

	ran := s.Worker("Grace").Runs("bin/commandments info redundant-else")

	s.Never(ran, "workers_cli")
}

func TestAWorkerIsRefusedTheJudgeByDefault(t *testing.T) {
	s := story.Begin(t)

	ran := s.Worker("Grace").Runs("journal sins --changes")

	s.Refuses(ran)
	s.Says(ran, "workers_cli")
}

func TestTheMainAgentRunsTheCommandmentsCliWhenWorkersAreDenied(t *testing.T) {
	s := story.Begin(t)

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
