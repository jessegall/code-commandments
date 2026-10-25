package bridge

import (
	"bufio"
	"crypto/sha1"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"net"
	"os"
	"path/filepath"
	"runtime"
	"time"

	"github.com/jessegall/code-commandments/contract"
)

// ServiceSocket is where the bridge of the kind ("roslyn", "mypy") a session keeps up for the project answers: a
// socket named for the bridge and the project, since a project's own path may be longer than a socket's may be, in
// /tmp, whose path is short wherever $TMPDIR points.
func ServiceSocket(kind, project string) string {
	sum := sha1.Sum([]byte(project))
	folder := "/tmp"
	if runtime.GOOS == "windows" {
		folder = os.TempDir()
	}

	return filepath.Join(folder, "code-commandments-"+kind+"-"+hex.EncodeToString(sum[:])[:12]+".sock")
}

// Service is the bridge of the kind the session keeps up for the project, reached at its socket; false when no session
// keeps one up for it, and a run starts its own.
func Service(kind, project string) (*Server, bool) {
	limit, err := quietLimit()
	if err != nil {
		return nil, false
	}
	connection, err := net.DialTimeout("unix", ServiceSocket(kind, project), 2*time.Second)
	if err != nil {
		return nil, false
	}
	stop := func() {
		connection.Close()
	}

	return &Server{command: []string{kind + "-serve", project}, input: connection, output: contract.NewReader(watch(connection, limit, stop)), connection: connection, stop: stop}, true
}

// Answer answers each connection's request through the bridge, one at a time, until the listener closes; a bridge
// that fails ends it, answering why.
func Answer(listener net.Listener, asked Asker) error {
	for {
		connection, err := listener.Accept()
		if errors.Is(err, net.ErrClosed) {
			return nil
		}
		if err != nil {
			continue
		}

		// A request that fails costs that request and nothing more. The service outlives it: a bridge that dies
		// under one request would otherwise take the service down with it, and a journal writing to the socket it
		// left behind reads a broken pipe, calls the plugin at fault and switches it off, which stops every
		// discipline being enforced until someone switches it on again. The reason goes to stderr, which is the
		// service's own log.
		if err := answer(connection, asked); err != nil {
			fmt.Fprintln(os.Stderr, "a request was not answered: "+err.Error())
		}
	}
}

// answer is one connection: its request line asked of the bridge, and every line of the stream written back.
func answer(connection net.Conn, asked Asker) error {
	defer connection.Close()

	line, err := bufio.NewReader(connection).ReadBytes('\n')
	if err != nil {
		return nil
	}
	var request Request
	if json.Unmarshal(line, &request) != nil {
		return nil
	}

	return asked.AskEach(request, func(_ contract.Line, raw []byte) error {
		connection.Write(raw)
		connection.Write([]byte{'\n'})

		return nil
	})
}
