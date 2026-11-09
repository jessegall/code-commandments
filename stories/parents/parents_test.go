//go:build story

package parents

import (
	"fmt"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/story"
)

const (
	serviceComesUp  = 60 * time.Second
	bridgeStarts    = 90 * time.Second
	goneAfterParent = 20 * time.Second
	goneAfterSignal = 5 * time.Second
	aParentStays    = 12 * time.Second

	hooksVerb = "journal-serve"
)

func begun(t *testing.T) *story.Story {
	t.Helper()

	s := story.Begin(t)
	s.WaitsForTheService("hooks", serviceComesUp)

	return s
}

func aServiceUnderAParent(t *testing.T, kind string) (*story.Story, *story.Service) {
	t.Helper()

	s := story.Begin(t)

	var service *story.Service

	s.Given("a throwaway "+kind+" service under a parent of its own", func() {
		s.WaitsForTheService("hooks", serviceComesUp)
		service = s.StartsAServiceUnderAParent(kind)
	})

	return s, service
}

func aServiceAndItsBridge(t *testing.T, kind string) (*story.Story, *story.Service, story.Process) {
	t.Helper()

	s := story.Begin(t)

	var (
		service *story.Service
		bridge  story.Process
	)

	s.Given("a throwaway "+kind+" service and its bridge", func() {
		s.WaitsForTheService("hooks", serviceComesUp)
		service = s.StartsAServiceUnderAParent(kind)
		bridge = service.Bridge(bridgeStarts)
	})

	return s, service, bridge
}

func aServiceAndItsFrozenBridge(t *testing.T) (*story.Story, *story.Service, story.Process) {
	t.Helper()

	s := story.Begin(t)

	var (
		service *story.Service
		bridge  story.Process
	)

	s.Given("a throwaway mypy service and its frozen bridge", func() {
		s.WaitsForTheService("hooks", serviceComesUp)
		service = s.StartsAServiceUnderAParent("mypy")
		bridge = service.Bridge(bridgeStarts)
		service.Freezes(bridge)
	})

	return s, service, bridge
}

func aServiceAndItsBridgeGoWithTheirParent(t *testing.T, kind string) {
	s, service, bridge := aServiceAndItsBridge(t, kind)

	service.EndsItsParent()

	service.IsGone(goneAfterParent)
	s.EventuallyGone(bridge.Pid, goneAfterParent)
	s.NoOrphans()
}

func stopsTheHooksServiceAndItComesBack(s *story.Story) {
	running := s.TheService(hooksVerb)

	s.StopsAService(running.Pid, story.Terminates)
	s.EventuallyGone(running.Pid, goneAfterParent)
	s.WaitsForTheService("hooks", serviceComesUp)
}

func TestTheHooksServiceStopsWhenItsParentIsGone(t *testing.T) {
	s, service := aServiceUnderAParent(t, "hooks")
	service.IsListedUnderItsParent()

	service.EndsItsParent()

	service.IsGone(goneAfterParent)
	s.NoOrphans()
}

func TestAPythonServiceAndItsBridgeGoWithTheirParent(t *testing.T) {
	aServiceAndItsBridgeGoWithTheirParent(t, "mypy")
}

func TestAFrontendServiceAndItsBridgeGoWithTheirParent(t *testing.T) {
	aServiceAndItsBridgeGoWithTheirParent(t, "frontend")
}

func TestAPHPServiceAndItsBridgeGoWithTheirParent(t *testing.T) {
	aServiceAndItsBridgeGoWithTheirParent(t, "php")
}

func TestACSharpServiceAndItsBridgeGoWithTheirParent(t *testing.T) {
	aServiceAndItsBridgeGoWithTheirParent(t, "roslyn")
}

func TestAFrozenBridgeGoesWithItsService(t *testing.T) {
	s, service, bridge := aServiceAndItsFrozenBridge(t)

	service.EndsItsParent()

	service.IsGone(goneAfterParent)
	s.EventuallyGone(bridge.Pid, goneAfterParent)
	s.NoOrphans()
}

func TestAServiceWhoseParentStaysKeepsRunningAndAnswering(t *testing.T) {
	s, service := aServiceUnderAParent(t, "hooks")

	s.LetsTimePass(aParentStays)

	service.IsListedUnderItsParent()
	service.Answers()
}

func TestAServiceStoppedByASignalTakesItsBridgeAlongAndSaysWhy(t *testing.T) {
	s, service, bridge := aServiceAndItsBridge(t, "mypy")

	s.StopsAService(fmt.Sprint(service.Pid), story.Terminates)

	s.EventuallyGone(bridge.Pid, goneAfterSignal)
	service.IsGone(goneAfterSignal)
	service.StopsAnswering()
	service.Wrote("by a signal")
	s.NoOrphans()
}

func TestAServiceStoppedByASignalTakesAFrozenBridgeAlong(t *testing.T) {
	s, service, bridge := aServiceAndItsFrozenBridge(t)

	s.StopsAService(fmt.Sprint(service.Pid), story.Terminates)

	s.EventuallyGone(bridge.Pid, goneAfterSignal)
	service.IsGone(goneAfterSignal)
	s.NoOrphans()
}

func TestAServiceWhoseParentIsGoneSaysWhyItStopped(t *testing.T) {
	s, service := aServiceUnderAParent(t, "hooks")

	service.EndsItsParent()

	service.IsGone(goneAfterParent)
	service.Wrote("its parent is gone")
	s.NoOrphans()
}

func TestTheJournalBringsAStoppedHooksServiceBack(t *testing.T) {
	s := begun(t)
	s.Given("the journal brought a stopped hooks service back", func() { stopsTheHooksServiceAndItComesBack(s) })

	answered := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Never(answered, "no service answered")
	s.Says(s.Asks("plugin", "service-log", "code-commandments", "hooks"), "by a signal")
	s.NoOrphans()
}

func TestStoppingTheHooksServiceAgainAndAgainLeavesExactlyOne(t *testing.T) {
	s := begun(t)

	for range 3 {
		stopsTheHooksServiceAndItComesBack(s)
	}

	running := 0

	for _, process := range s.Processes() {
		if strings.Contains(process.Command, hooksVerb) {
			running++
		}
	}

	if running != 1 {
		s.Fails(fmt.Sprintf("%d hooks services run after three stops, where one is meant", running))
	}

	s.NoOrphans()
}
