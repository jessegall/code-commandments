package service

import (
	"bufio"
	"bytes"
	"errors"
	"net"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/contract"
)

// goneBridge is a bridge whose process has died: every question fails as a bridge that is gone.
type goneBridge struct{}

func (goneBridge) AskTallied(bridge.Request, func()) (*contract.Stream, error) {
	return nil, bridge.Failed([]string{"a bridge"}, errors.New("it is gone"), "")
}

func (goneBridge) AskEach(bridge.Request, func(contract.Line, []byte) error) error {
	return bridge.Failed([]string{"a bridge"}, errors.New("it is gone"), "")
}

func (goneBridge) Close() error { return nil }

// freshBridge is a bridge that answers every question with one line.
type freshBridge struct{}

func (freshBridge) AskTallied(bridge.Request, func()) (*contract.Stream, error) {
	return &contract.Stream{}, nil
}

func (freshBridge) AskEach(_ bridge.Request, each func(contract.Line, []byte) error) error {
	return each(contract.Line{}, []byte(`{"fresh":true}`))
}

func (freshBridge) Close() error { return nil }

// reborn hands a service a fresh bridge each time it asks for one of its own, and counts the times.
type reborn struct{ started *int }

func (r reborn) Open(scan.Bridge, []string) (bridge.Asker, error) { return freshBridge{}, nil }

func (r reborn) Own(scan.Bridge, []string) (bridge.Asker, bool, error) {
	*r.started++

	return freshBridge{}, true, nil
}

func TestAServiceWhoseBridgeIsGoneStartsANewOneAndAnswersOn(t *testing.T) {
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

	started := 0
	serve := Serve{Bridge{Kind: "php", Label: "PHP", Reach: reborn{&started}}}
	warned := &bytes.Buffer{}

	go serve.answering(listener, t.TempDir(), goneBridge{}, cli.Console{Out: &bytes.Buffer{}, Err: warned})

	asked := func() string {
		connection, err := net.Dial("unix", listener.Addr().String())
		if err != nil {
			t.Fatal(err)
		}
		defer connection.Close()

		connection.SetDeadline(time.Now().Add(5 * time.Second))
		connection.Write([]byte("{}\n"))
		line, _ := bufio.NewReader(connection).ReadString('\n')

		return line
	}

	if lost := asked(); lost != "" {
		t.Fatalf("the question put to the bridge that is gone was answered with %q", lost)
	}

	if answered := asked(); answered != "{\"fresh\":true}\n" {
		t.Errorf("after its bridge was gone the service answered %q, and it should have started a new bridge", answered)
	}

	if started != 1 {
		t.Errorf("the service started %d new bridges for the one that was gone", started)
	}
}
