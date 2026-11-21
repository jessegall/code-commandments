package scan

import (
	"bufio"
	"bytes"
	"errors"
	"net"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/contract"
)

func TestAWarmBridgeOfAnOlderBuildIsRefusedAndTheRunFallsBackToItsOwn(t *testing.T) {
	project, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	chdir(t, project)

	listener, err := net.Listen("unix", bridge.ServiceSocket("php", project))
	if err != nil {
		t.Fatal(err)
	}
	defer listener.Close()
	go func() {
		for {
			connection, err := listener.Accept()
			if err != nil {
				return
			}
			bufio.NewReader(connection).ReadBytes('\n')
			connection.Write([]byte(`{"header": {"contract": "tree", "version": 4, "language": "php", "bridge": {"name": "php-bridge", "version": "7.20.30"}, "roots": ["` + project + `"]}}` + "\n" +
				`{"trailer": {"files": 0}}` + "\n"))
			connection.Close()
		}
	}()

	said := swapStderr(t)
	ownStarted := false
	held := &process{
		held: Bridge{Kind: "php", Label: "PHP"},
		reach: Process{Command: func([]string) ([]string, error) {
			ownStarted = true

			return nil, errors.New("no bridge of its own in this test")
		}},
		roots: []string{project},
	}

	stream, err := held.AskTallied(bridge.Request{Paths: bridge.Listed(project)}, func() {})
	words := said()

	if stream != nil || err == nil {
		t.Errorf("a version 4 answer gave a stream %v and the error %v; nothing it holds may be published", stream, err)
	}
	if !ownStarted {
		t.Error("the run did not fall back to a bridge of its own")
	}
	if !bytes.Contains([]byte(words), []byte("could not answer")) || !bytes.Contains([]byte(words), []byte("version 4")) {
		t.Errorf("the run did not say the warm bridge answered in version 4: %q", words)
	}

	handed := 0
	err = held.AskEach(bridge.Request{Paths: bridge.Listed(project)}, func(contract.Line, []byte) error {
		handed++

		return nil
	})
	if handed != 0 || err == nil {
		t.Errorf("asked line by line, a version 4 answer handed on %d lines with the error %v", handed, err)
	}
}
