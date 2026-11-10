package bridge

import (
	"syscall"
	"testing"
	"time"
)

func TestAServedBridgeThatIsKilledIsStartedAgainWithNoRequest(t *testing.T) {
	server, err := Serve([]string{"sh", "-c", answering})
	if err != nil {
		t.Fatal(err)
	}
	defer server.Close()

	killed := server.pid()
	if err := syscall.Kill(killed, syscall.SIGKILL); err != nil {
		t.Fatal(err)
	}

	deadline := time.Now().Add(10 * time.Second)
	for server.pid() == killed {
		if time.Now().After(deadline) {
			t.Fatal("the killed bridge was not started again, and nothing was asked of it meanwhile")
		}

		time.Sleep(20 * time.Millisecond)
	}

	if _, err := server.Ask(Request{}); err != nil {
		t.Errorf("the bridge started in its place does not answer: %v", err)
	}
}

func TestAClosedBridgeIsNotStartedAgain(t *testing.T) {
	server, err := Serve([]string{"sh", "-c", answering})
	if err != nil {
		t.Fatal(err)
	}

	closed := server.pid()
	if err := server.Close(); err != nil {
		t.Fatal(err)
	}

	time.Sleep(300 * time.Millisecond)

	if again := server.pid(); again != closed {
		t.Errorf("a bridge that was closed came back as process %d", again)
	}
}
