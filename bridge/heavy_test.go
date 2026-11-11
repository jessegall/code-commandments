package bridge

import (
	"os"
	"testing"
	"time"
)

const answering = `while read request; do printf '%s\n%s\n' '` + header + `' '` + trailer + `'; done`

func weighing(t *testing.T, megabytes func(pid int) int) {
	t.Helper()

	measured := footprintOf
	footprintOf = megabytes

	t.Cleanup(func() {
		footprintOf = measured
	})
}

func pidAfterEachOf(t *testing.T, server *Server, requests int) []int {
	t.Helper()

	var pids []int
	finishes(t, 20*time.Second, func() {
		for asked := range requests {
			if _, err := server.Ask(Request{Paths: []string{"/abs/src"}}); err != nil {
				t.Errorf("request %d: %v", asked+1, err)

				return
			}

			pids = append(pids, server.pid())
		}
	})

	return pids
}

func TestAServedBridgePastItsMemoryLimitIsReplacedBeforeItsNextRequest(t *testing.T) {
	t.Setenv(memoryVariable, "100")

	server, err := Serve([]string{"sh", "-c", answering})
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()

	grown := server.pid()
	weighing(t, func(pid int) int {
		if pid == grown {
			return 101
		}

		return 5
	})

	pids := pidAfterEachOf(t, server, 3)

	if len(pids) != 3 {
		t.Fatalf("three requests were answered %d times", len(pids))
	}
	if pids[0] == grown {
		t.Errorf("the bridge that grew past its limit, process %d, is still the one answering", grown)
	}
	if pids[1] != pids[0] || pids[2] != pids[0] {
		t.Errorf("a bridge under its limit was replaced: processes %v", pids)
	}
}

func TestAServedBridgeUnderItsMemoryLimitIsKept(t *testing.T) {
	t.Setenv(memoryVariable, "100")
	weighing(t, func(int) int { return 100 })

	server, err := Serve([]string{"sh", "-c", answering})
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()

	started := server.pid()

	for _, pid := range pidAfterEachOf(t, server, 2) {
		if pid != started {
			t.Errorf("a bridge at its limit and not past it was replaced: process %d, not %d", pid, started)
		}
	}
}

func TestAMemoryLimitOfZeroKeepsAServedBridgeOfAnySize(t *testing.T) {
	t.Setenv(memoryVariable, "0")
	weighing(t, func(int) int { return 1 << 20 })

	server, err := Serve([]string{"sh", "-c", answering})
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()

	started := server.pid()

	for _, pid := range pidAfterEachOf(t, server, 2) {
		if pid != started {
			t.Errorf("a bridge with no limit was replaced: process %d, not %d", pid, started)
		}
	}
}

func TestAMemoryLimitThatIsNoNumberRefusesToServe(t *testing.T) {
	t.Setenv(memoryVariable, "plenty")

	if server, err := Serve([]string{"sh", "-c", answering}); err == nil {
		server.Close()
		t.Error("a limit of \"plenty\" megabytes still served")
	}
}

func TestTheFootprintOfThisProcessIsMeasured(t *testing.T) {
	if held := Footprint(os.Getpid()); held < 1 {
		t.Errorf("this test process weighs %d MB", held)
	}
}
