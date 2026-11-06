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

func TestAServedBridgeThatLingersAfterItsInputEndsIsStopped(t *testing.T) {
	server, err := Serve([]string{"sh", "-c", `while read request; do :; done; sleep 60`})
	if err != nil {
		t.Fatal(err)
	}
	finishes(t, 3*grace, func() {
		server.Close()
	})
}

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

func TestABridgeLimitThatIsNotADurationIsRefused(t *testing.T) {
	t.Setenv(quietVariable, "ten minutes")
	if _, err := Serve([]string{"sh", "-c", "cat"}); err == nil || !strings.Contains(err.Error(), quietVariable) {
		t.Errorf("a limit of %q was taken: %v", "ten minutes", err)
	}
}

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
