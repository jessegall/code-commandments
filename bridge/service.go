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

func ServiceSocket(kind, project string) string {
	sum := sha1.Sum([]byte(canonical(project)))
	folder := "/tmp"
	if runtime.GOOS == "windows" {
		folder = os.TempDir()
	}

	return filepath.Join(folder, "code-commandments-"+kind+"-"+hex.EncodeToString(sum[:])[:12]+".sock")
}

func canonical(project string) string {
	resolved, err := filepath.EvalSymlinks(project)
	if err != nil {
		return filepath.Clean(project)
	}

	return filepath.Clean(resolved)
}

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

func Answer(listener net.Listener, asked Asker) error {
	for {
		connection, err := listener.Accept()
		if errors.Is(err, net.ErrClosed) {
			return nil
		}
		if err != nil {
			continue
		}

		err = answer(connection, asked)
		if failed := (*BridgeFailed)(nil); errors.As(err, &failed) {
			return err
		}
		if err != nil {
			fmt.Fprintln(os.Stderr, "a request was not answered: "+err.Error())
		}
	}
}

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
