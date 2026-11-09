package bridge

import (
	"context"
	"testing"
	"time"
)

func stoppableOnItsOwn(t *testing.T) {
	kept, keptStop := everyBridge, StopEveryBridge
	everyBridge, StopEveryBridge = context.WithCancel(context.Background())
	t.Cleanup(func() {
		StopEveryBridge()
		everyBridge, StopEveryBridge = kept, keptStop
	})
}

func TestStoppingEveryBridgeEndsOneBusyWithARequest(t *testing.T) {
	stoppableOnItsOwn(t)

	server, err := Serve([]string{"sh", "-c", "exec sleep 60"})
	if err != nil {
		t.Fatal(err)
	}

	answered := make(chan error, 1)
	go func() {
		_, err := server.Ask(Request{Paths: []string{"a.py"}})
		answered <- err
	}()

	StopEveryBridge()

	select {
	case err := <-answered:
		if err == nil {
			t.Fatal("a bridge that was stopped answered its request")
		}
	case <-time.After(5 * time.Second):
		t.Fatal("the bridge kept working on its request after it was stopped")
	}
}

func TestNoBridgeStartsAfterEveryBridgeWasStopped(t *testing.T) {
	stoppableOnItsOwn(t)
	StopEveryBridge()

	if _, err := Serve([]string{"sh", "-c", "exec sleep 60"}); err == nil {
		t.Fatal("a bridge started after every bridge was stopped")
	}
}
