//go:build story

package install

import (
	"fmt"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/story"
)

const (
	plugin        = "code-commandments"
	localBuild    = "../../bin/plugin-local/plugin"

	serviceComesUp = 90 * time.Second
	serviceGoes    = 60 * time.Second
)

var serviceVerbs = []string{"journal-serve", "roslyn-serve", "mypy-serve", "frontend-serve", "php-serve"}

func built(s *story.Story) string {
	folder, err := filepath.Abs(localBuild)
	if err != nil {
		s.Fails(err.Error())
	}

	return folder
}

func upgrades(s *story.Story) story.Ran {
	return s.Asks("plugin", "upgrade", plugin, "--from", built(s), "--yes", "--reason", "a story proves an upgrade")
}

func oneOfEachService(s *story.Story) {
	running := s.Processes()

	for _, verb := range serviceVerbs {
		matching := 0

		for _, process := range running {
			if strings.Contains(process.Command, verb) {
				matching++
			}
		}

		if matching != 1 {
			s.Fails(fmt.Sprintf("%d processes run %s, want one: %v", matching, verb, running))
		}
	}
}

func TestAnInstalledPluginRunsOneOfEachService(t *testing.T) {
	s := story.Begin(t)
	s.WaitsForTheService("hooks", serviceComesUp)

	oneOfEachService(s)
	s.NoOrphans()
}

func TestUpgradingToTheSameBuildLeavesOneOfEachService(t *testing.T) {
	s := story.Begin(t)
	t.Cleanup(func() { s.WaitsForTheService("hooks", serviceComesUp) })

	s.Exits(upgrades(s), 0)

	s.WaitsForTheService("hooks", serviceComesUp)
	oneOfEachService(s)
	s.NoOrphans()
}

func TestInstallingTwiceLeavesNoDuplicate(t *testing.T) {
	s := story.Begin(t)
	t.Cleanup(func() { s.WaitsForTheService("hooks", serviceComesUp) })

	s.Exits(upgrades(s), 0)
	s.Exits(upgrades(s), 0)

	s.WaitsForTheService("hooks", serviceComesUp)
	s.SaysOnce(s.Asks("alias", "list"), "commandments-judge-changes")
	oneOfEachService(s)
}

func TestRemovingThePluginLeavesNothingRunning(t *testing.T) {
	s := story.Begin(t)
	t.Cleanup(func() {
		s.Asks("plugin", "install", built(s), "--yes", "--reason", "a story puts the plugin back")
		s.WaitsForTheService("hooks", serviceComesUp)
	})
	running := s.Processes()

	s.Exits(s.Asks("plugin", "remove", plugin, "--reason", "a story proves a removal leaves nothing"), 0)

	for _, process := range running {
		s.EventuallyGone(process.Pid, serviceGoes)
	}

	s.NoOrphans()
}
