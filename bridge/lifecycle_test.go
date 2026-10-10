package bridge

import (
	"bufio"
	"errors"
	"net"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/contract"
)

const header = `{"header": {"contract": "tree", "version": 1, "language": "php", "bridge": {"name": "test", "version": "1"}, "roots": ["/abs/src"]}}`

// finishes fails the test when run has not returned in time, rather than hanging the suite on it.
func finishes(t *testing.T, limit time.Duration, run func()) {
	t.Helper()
	done := make(chan struct{})
	go func() {
		run()
		close(done)
	}()
	select {
	case <-done:
	case <-time.After(limit):
		t.Fatalf("still running after %s", limit)
	}
}

// TestARunReadsPastWhatABridgeWritesAfterItsTrailer holds Run to draining the bridge's output: a bridge that writes
// more than a pipe holds after its trailer would otherwise block on the write, and Run on its exit.
func TestARunReadsPastWhatABridgeWritesAfterItsTrailer(t *testing.T) {
	script := `printf '%s\n%s\n' '` + header + `' '{"trailer": {"files": 0}}'; head -c 1048576 /dev/zero`
	finishes(t, 20*time.Second, func() {
		stream, _, ran, err := Run([]string{"sh", "-c", script})
		if err != nil || !ran {
			t.Errorf("the run failed: ran %v, %v", ran, err)
		}
		if stream == nil {
			t.Error("no stream was read")
		}
	})
}

// TestAServedBridgeThatDiesSaysAllItWroteToStderr holds a served bridge's failure to carrying everything the bridge
// wrote to stderr before it died, read once the bridge has written it.
func TestAServedBridgeThatDiesSaysAllItWroteToStderr(t *testing.T) {
	said := strings.Repeat("x", 200000) + "END"
	server, err := Serve([]string{"sh", "-c", `head -c 200000 /dev/zero | tr '\0' x >&2; printf END >&2; exit 3`})
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()
	finishes(t, 20*time.Second, func() {
		_, err := server.Ask(Request{Paths: []string{"/abs/src"}})
		var failed *BridgeFailed
		if !errors.As(err, &failed) {
			t.Errorf("the answer is %v, not a bridge failure", err)

			return
		}
		if failed.Stderr != said {
			t.Errorf("the failure carries %d bytes of stderr, the bridge wrote %d", len(failed.Stderr), len(said))
		}
	})
}

const trailer = `{"trailer": {"files": 0}}`

// TestARunStopsABridgeThatStaysSilent holds Run to ending, Silent, when the bridge writes nothing for as long as a
// read may wait, even while the process it started lives on holding the pipe.
func TestARunStopsABridgeThatStaysSilent(t *testing.T) {
	t.Setenv(quietVariable, "2s")
	finishes(t, 20*time.Second, func() {
		_, _, ran, err := Run([]string{"sh", "-c", `printf '%s\n' '` + header + `'; sleep 60`})
		var silent Silent
		if ran || !errors.As(err, &silent) {
			t.Errorf("the run answered ran %v, %v, not that the bridge went silent", ran, err)
		}
	})
}

// TestARunWaitsOnABridgeThatIsSlowButWriting holds the limit to one read's wait, not the run's length: a bridge
// that writes a little at a time runs past the limit and is read whole.
func TestARunWaitsOnABridgeThatIsSlowButWriting(t *testing.T) {
	t.Setenv(quietVariable, "2s")
	script := `printf '%s\n' '` + header + `'; for part in '{"trailer": ' '{"files": ' '0}' '}'; do sleep 1; printf '%s' "$part"; done; echo`
	finishes(t, 20*time.Second, func() {
		stream, _, ran, err := Run([]string{"sh", "-c", script})
		if !ran || err != nil || stream == nil {
			t.Errorf("a slow bridge was not read whole: ran %v, %v", ran, err)
		}
	})
}

// TestARunSaysWhyABridgeThatDiedFailed holds a dead bridge's failure to the one line it wrote, as the C# bridge's
// failure says it.
func TestARunSaysWhyABridgeThatDiedFailed(t *testing.T) {
	finishes(t, 20*time.Second, func() {
		stream, errs, ran, err := Run([]string{"sh", "-c", `echo 'roslyn-bridge: there is no file or folder at /gone to read' >&2; exit 1`})
		if ran || stream != nil {
			t.Fatalf("a bridge that exited 1 ran: %v", err)
		}
		failure := RoslynFailure(Failed([]string{"roslyn-bridge"}, err, errs))
		if want := "the C# bridge failed: roslyn-bridge: there is no file or folder at /gone to read, so C# is not judged; everything else is"; failure.Error() != want {
			t.Errorf("the failure reads %q, not %q", failure, want)
		}
		if _, skipped := failure.(Unavailable); !skipped {
			t.Error("a failed C# bridge stops the run instead of leaving C# unread")
		}
	})
}

// TestAServedBridgeThatStaysSilentIsStopped holds Ask to ending, Silent and saying so, when a served bridge takes a
// request and never answers, and Close to returning once it has.
func TestAServedBridgeThatStaysSilentIsStopped(t *testing.T) {
	t.Setenv(quietVariable, "2s")
	server, err := Serve([]string{"sh", "-c", `read request; sleep 60`})
	if err != nil {
		t.Fatal(err)
	}
	finishes(t, 20*time.Second, func() {
		_, err := server.Ask(Request{Paths: []string{"/abs/src"}})
		if reason := RoslynFailure(err).Error(); !strings.Contains(reason, "the C# bridge failed: it wrote nothing for 2s") {
			t.Errorf("the answer is %q, not that the bridge went silent", reason)
		}
		server.Close()
	})
}

// TestAServedBridgeIsNotStoppedWhileItsCallerIsBusy holds the limit to the wait inside a read: a caller that
// takes longer than the limit between requests finds the bridge still answering.
func TestAServedBridgeIsNotStoppedWhileItsCallerIsBusy(t *testing.T) {
	t.Setenv(quietVariable, "1s")
	server, err := Serve([]string{"sh", "-c", `while read request; do printf '%s\n%s\n' '` + header + `' '` + trailer + `'; done`})
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()
	finishes(t, 20*time.Second, func() {
		for asked := range 2 {
			if _, err := server.Ask(Request{Paths: []string{"/abs/src"}}); err != nil {
				t.Fatalf("request %d: %v", asked+1, err)
			}
			time.Sleep(2 * time.Second)
		}
	})
}

// TestAServedBridgeThatLingersAfterItsInputEndsIsStopped holds Close to returning when the bridge does not end
// with its input.
func TestAServedBridgeThatLingersAfterItsInputEndsIsStopped(t *testing.T) {
	server, err := Serve([]string{"sh", "-c", `while read request; do :; done; sleep 60`})
	if err != nil {
		t.Fatal(err)
	}
	finishes(t, 3*grace, func() {
		server.Close()
	})
}

// TestTheBridgeServiceThatStaysSilentIsLetGo holds a session's service to the same limit: a socket that takes the
// request and never answers ends the read, Silent.
func TestTheBridgeServiceThatStaysSilentIsLetGo(t *testing.T) {
	t.Setenv(quietVariable, "2s")
	project := t.TempDir()
	listener, err := net.Listen("unix", ServiceSocket("roslyn", project))
	if err != nil {
		t.Fatal(err)
	}
	defer listener.Close()
	go func() {
		connection, err := listener.Accept()
		if err != nil {
			return
		}
		defer connection.Close()
		bufio.NewReader(connection).ReadBytes('\n')
		time.Sleep(30 * time.Second)
	}()
	server, kept := Service("roslyn", project)
	if !kept {
		t.Fatal("the service was not reached")
	}
	defer server.Close()
	finishes(t, 20*time.Second, func() {
		_, err := server.Ask(Request{Paths: []string{project}})
		var silent Silent
		if !errors.As(err, &silent) {
			t.Errorf("the answer is %v, not that the service went silent", err)
		}
	})
}

// TestABridgeLimitThatIsNotADurationIsRefused holds a mistyped limit to failing the run, never to waiting for ever.
func TestABridgeLimitThatIsNotADurationIsRefused(t *testing.T) {
	t.Setenv(quietVariable, "ten minutes")
	if _, err := Serve([]string{"sh", "-c", "cat"}); err == nil || !strings.Contains(err.Error(), quietVariable) {
		t.Errorf("a limit of %q was taken: %v", "ten minutes", err)
	}
}

// TestAnErrorTheCallerRaisesIsNeverTheBridgesFailure holds AskEach to handing back the caller's own error as it
// is: a failed bridge leaves C# unjudged, so the caller's failure must never pass for one.
func TestAnErrorTheCallerRaisesIsNeverTheBridgesFailure(t *testing.T) {
	server, err := Serve([]string{"sh", "-c", `while read request; do printf '%s\n%s\n' '` + header + `' '` + trailer + `'; done`})
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()
	refused := errors.New("the caller refused the line")
	finishes(t, 20*time.Second, func() {
		err := server.AskEach(Request{Paths: []string{"/abs/src"}}, func(contract.Line, []byte) error {
			return refused
		})
		var failed *BridgeFailed
		if err != refused || errors.As(err, &failed) {
			t.Errorf("the caller's error came back as %v", err)
		}
	})
}
