package bridge

import (
	"errors"
	"strings"
	"testing"
	"time"
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
