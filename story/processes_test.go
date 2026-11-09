package story

import (
	"testing"
	"time"
)

func TestTheProcessesAreReadOffTheRelaysLines(t *testing.T) {
	s, asked := storyOver(t, func(request) Ran {
		return Ran{Out: "12\t3\t/p/.journal/plugins/x/commandments-release journal-serve\n13\t12\tnode bridge.mjs --serve --cache=a b\n"}
	})

	processes := s.Processes()

	if len(processes) != 2 || processes[1] != (Process{Pid: "13", Parent: "12", Command: "node bridge.mjs --serve --cache=a b"}) {
		t.Errorf("the processes read are %v", processes)
	}

	if kinds := kindsOf(asked); len(kinds) != 1 || kinds[0] != "processes" {
		t.Errorf("the relay was asked %v", kinds)
	}
}

func TestOnlyAProcessWhoseParentIsGoneIsAnOrphan(t *testing.T) {
	orphans := orphansIn([]Process{{Pid: "5", Parent: "1", Command: "left"}, {Pid: "6", Parent: "5", Command: "held"}})

	if len(orphans) != 1 || orphans[0].Pid != "5" {
		t.Errorf("the orphans are %v", orphans)
	}
}

func TestAStoryStopsAServiceAndWaitsThroughTheRelay(t *testing.T) {
	s, asked := storyOver(t, quiet)

	s.StopsAService("12", Terminates)
	s.WaitsForTheService("hooks", 30*time.Second)

	stop, wait := (*asked)[0], (*asked)[1]
	if stop.Kind != "stop-service" || stop.Pid != "12" || stop.Action != "SIGTERM" {
		t.Errorf("the stop asked is %+v", stop)
	}

	if wait.Kind != "wait-service" || wait.Name != "hooks" || wait.Many != 30 {
		t.Errorf("the wait asked is %+v", wait)
	}
}

func TestAProcessGoneIsSeenAsGone(t *testing.T) {
	s, _ := storyOver(t, quiet)

	s.EventuallyGone("12", time.Second)
}

func TestAThrowawayServiceIsReadOffTheRelaysAnswerAndEndedByItsParent(t *testing.T) {
	var asked *[]request

	t.Run("a story that starts one", func(inner *testing.T) {
		var s *Story

		s, asked = storyOver(inner, func(one request) Ran {
			if one.Action == "start" {
				return Ran{Out: `{"pid": 12, "parent": 11, "socket": "/tmp/story-a/health.sock", "data": "/tmp/story-a/data"}`}
			}

			return Ran{}
		})

		service := s.StartsAServiceUnderAParent("hooks")
		service.EndsItsParent()

		if service.Pid != 12 || service.Parent != 11 || service.Socket != "/tmp/story-a/health.sock" || service.DataFolder != "/tmp/story-a/data" {
			inner.Errorf("the service read is %+v", service)
		}
	})

	if last := (*asked)[len(*asked)-1]; last.Kind != "throwaway" || last.Action != "teardown" {
		t.Errorf("the story's last step at the end was %+v, where it tears the services down", last)
	}
}
