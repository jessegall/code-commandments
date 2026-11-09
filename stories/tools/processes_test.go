//go:build story

package tools

import (
	"testing"
	"time"

	"github.com/jessegall/code-commandments/story"
)

const serviceReturnsWithin = 60 * time.Second

func TestTheRelaySeesThePluginsProcesses(t *testing.T) {
	s := story.Begin(t)
	s.Exits(s.WaitsForTheService("hooks", serviceReturnsWithin), 0)

	service := s.TheService("journal-serve")

	if service.Parent == "" || service.Parent == "1" {
		s.Fails("the hooks service has no parent of its own: " + service.Parent)
	}
}

func TestTheRelayStopsOnlyAListedPid(t *testing.T) {
	s := story.Begin(t)
	t.Cleanup(func() { s.WaitsForTheService("hooks", serviceReturnsWithin) })
	s.Exits(s.WaitsForTheService("hooks", serviceReturnsWithin), 0)

	s.Refuses(s.StopsAService(s.StartsAPlainProcess(), story.Kills))

	service := s.TheService("journal-serve")
	s.Exits(s.StopsAService(service.Pid, story.Terminates), 0)
	s.EventuallyGone(service.Pid, 20*time.Second)

	s.Exits(s.WaitsForTheService("hooks", serviceReturnsWithin), 0)
}

func TestAThrowawayServiceStartsUnderItsOwnParentAndIsTornDown(t *testing.T) {
	s := story.Begin(t)

	service := s.StartsAServiceUnderAParent("hooks")
	service.IsListedUnderItsParent()

	service.EndsItsParent()
}

func TestARefusalToEndAParentItDidNotStart(t *testing.T) {
	s := story.Begin(t)

	s.Refuses(s.EndsAThrowawayParent(s.StartsAPlainProcess()))
}
