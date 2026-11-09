package bridge

import (
	"context"
	"errors"
	"net"
	"os"
	"path/filepath"
	"syscall"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/contract"
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

func TestAFrozenBridgeIsKilledInsideTheTimeAServiceTakesToLeave(t *testing.T) {
	stoppableOnItsOwn(t)

	process, stop := launch([]string{"sh", "-c", "exec sleep 60"})
	defer stop()

	if err := process.Start(); err != nil {
		t.Fatal(err)
	}

	if err := process.Process.Signal(syscall.SIGSTOP); err != nil {
		t.Fatal(err)
	}

	StopEveryBridge()

	ended := make(chan error, 1)
	go func() { ended <- process.Wait() }()

	select {
	case <-ended:
	case <-time.After(3 * time.Second):
		process.Process.Kill()
		t.Fatal("a frozen bridge was still there when the service would have left")
	}
}

func TestNoBridgeStartsAfterEveryBridgeWasStopped(t *testing.T) {
	stoppableOnItsOwn(t)
	StopEveryBridge()

	if _, err := Serve([]string{"sh", "-c", "exec sleep 60"}); err == nil {
		t.Fatal("a bridge started after every bridge was stopped")
	}
}

// deadBridge is a bridge whose process has gone, so every question fails.
type deadBridge struct{}

func (deadBridge) AskTallied(Request, func()) (*contract.Stream, error) {
	return nil, Failed([]string{"a bridge"}, errors.New("it is gone"), "")
}

func (deadBridge) AskEach(Request, func(contract.Line, []byte) error) error {
	return Failed([]string{"a bridge"}, errors.New("it is gone"), "")
}

func (deadBridge) Close() error {
	return nil
}

func TestAServiceEndsWhenItsBridgeHasDied(t *testing.T) {
	folder, err := os.MkdirTemp("/tmp", "s")
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { os.RemoveAll(folder) })

	listener, err := net.Listen("unix", filepath.Join(folder, "a.sock"))
	if err != nil {
		t.Fatal(err)
	}
	defer listener.Close()

	ended := make(chan error, 1)
	go func() { ended <- Answer(listener, deadBridge{}) }()

	connection, err := net.Dial("unix", listener.Addr().String())
	if err != nil {
		t.Fatal(err)
	}
	defer connection.Close()

	connection.Write([]byte("{}\n"))

	select {
	case err := <-ended:
		if err == nil {
			t.Error("a service whose bridge died ended as though nothing was wrong")
		}
	case <-time.After(2 * time.Second):
		t.Fatal("a service whose bridge died went on answering")
	}
}
