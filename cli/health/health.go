// Package health answers the one question the agent journal asks a service about itself: are you up?
package health

import (
	"bufio"
	"encoding/json"
	"errors"
	"fmt"
	"net"
	"os"
	"path/filepath"
	"strconv"
	"time"
)

const (
	Socket   = "COMMANDMENTS_HEALTH_SOCKET"
	Request  = `{"health":1}`
	draining = 2 * time.Second

	longestSocket = 103
)

// up is what a service says about itself.
type up struct {
	Health  string `json:"health"`
	Service string `json:"service"`
}

func Answer(service string) string {
	said, err := json.Marshal(up{Health: "up", Service: service})
	if err != nil {
		return `{"health":"up"}`
	}

	return string(said)
}

func Asked(line map[string]any) bool {
	if _, named := line["health"]; named {
		return true
	}

	event, _ := line["event"].(string)

	return event == "health"
}

func Serve(service string) func() {
	close, unanswerable := ServeOn(os.Getenv(Socket), service)
	if unanswerable != "" {
		fmt.Fprintln(os.Stderr, unanswerable)
	}

	return close
}

func ServeOn(path, service string) (func(), string) {
	watchTheParent()

	if path == "" {
		return func() {}, ""
	}

	if len(path) > longestSocket {
		return func() {}, Unanswerable(path, service, errors.New("the path is "+strconv.Itoa(len(path))+
			" characters, past the "+strconv.Itoa(longestSocket)+" this platform allows for a socket"))
	}

	os.MkdirAll(filepath.Dir(path), 0o755)
	os.Remove(path)

	listener, err := net.Listen("unix", path)
	if err != nil {
		return func() {}, Unanswerable(path, service, err)
	}

	os.Chmod(path, 0o600)
	go answer(listener, Answer(service))

	return func() { listener.Close() }, ""
}

func Unanswerable(path, service string, why error) string {
	return "⚠ the " + service + " service cannot be asked whether it is up: " + why.Error() + " (" + path +
		"). It is RUNNING and judging; anything that asks will read it as stopped"
}

func answer(listener net.Listener, said string) {
	for {
		connection, err := listener.Accept()
		if err != nil {
			if errors.Is(err, net.ErrClosed) {
				return
			}

			continue
		}

		go func() {
			defer connection.Close()
			connection.Write([]byte(said + "\n"))
			connection.SetDeadline(time.Now().Add(draining))
			bufio.NewReader(connection).ReadString('\n')
		}()
	}
}
