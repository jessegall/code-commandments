package health

import (
	"net"
	"path/filepath"
	"strings"
	"testing"
)

func TestAPathPastWhatAPlatformAllowsIsNamedWithItsLength(t *testing.T) {
	long := filepath.Join(shortFolder(t), strings.Repeat("a", 120)+".sock")

	close, unanswerable := ServeOn(long, "mypy")
	defer close()

	if unanswerable == "" {
		t.Fatal("a socket path of over 120 characters opened, so nothing says why a service cannot be asked")
	}

	for _, named := range []string{"mypy", "characters", "RUNNING", long} {
		if !strings.Contains(unanswerable, named) {
			t.Errorf("the words do not name %s: %s", named, unanswerable)
		}
	}
}

func TestAServiceThatOpensItsSocketSaysNothing(t *testing.T) {
	path := filepath.Join(shortFolder(t), "x.sock")

	close, unanswerable := ServeOn(path, "hooks")
	defer close()

	if unanswerable != "" {
		t.Fatalf("a service that opened its socket says it cannot be asked: %s", unanswerable)
	}

	talk, err := net.Dial("unix", path)
	if err != nil {
		t.Fatalf("the socket it said it opened does not answer: %v", err)
	}
	defer talk.Close()
}

func TestNoSocketNamedIsNotAFailure(t *testing.T) {
	close, unanswerable := ServeOn("", "hooks")
	defer close()

	if unanswerable != "" {
		t.Errorf("a run with no health socket named reads as a failure: %s", unanswerable)
	}
}
