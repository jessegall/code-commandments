// Package health answers the one question the agent journal asks a service about itself: are you up?
//
// The journal calls each service's health endpoint on a schedule and allows 200ms for the answer. A service that
// does not answer in time is restarted, and five silences in a row mark the whole plugin broken and alert the user.
// So the answer is given from NOTHING: nothing judged, no file read, no bridge asked. It is a fact the process
// knows by running, and the moment it has to work anything out to say it, a loaded machine makes it late and a
// working service is killed for being busy.
//
// It is answered on a listener of its own, each question on its own goroutine, so the service's own work is never
// between the journal and the answer: a bridge halfway through parsing a project is UP, and a judge that takes
// twenty seconds is a judge, not a fault.
package health

import (
	"bufio"
	"encoding/json"
	"errors"
	"net"
	"os"
	"path/filepath"
	"time"
)

const (
	// Socket is the variable the journal names a service's health socket in, one socket per service. The manifest
	// declares this name so the journal knows what to set, and a test holds the two to each other: the journal
	// makes the path, since a socket's name has a length limit a project's own folder would run past.
	Socket = "COMMANDMENTS_HEALTH_SOCKET"
	// Request is the line the journal writes, which the manifest declares too. Nothing in it is read: the question
	// is the connection, and the answer is written the moment one is accepted.
	Request = `{"health":1}`
	// draining is how long a question is read for after the answer went out. The line is drained rather than
	// ignored, so a journal still writing its question is never cut off mid-write, and dropped at the deadline, so
	// a connection nobody finishes writing on cannot hold a goroutine for the life of the service.
	draining = 2 * time.Second
)

// up is what a service says about itself.
type up struct {
	Health  string `json:"health"`
	Service string `json:"service"`
}

// Answer is what the named service says when it is asked: that it is up, and which service answered.
func Answer(service string) string {
	said, err := json.Marshal(up{Health: "up", Service: service})
	if err != nil {
		return `{"health":"up"}`
	}

	return string(said)
}

// Asked says whether the line the journal wrote is the health question rather than an event of the agent's work, so
// a service whose socket already answers the journal's events answers this one there too.
func Asked(line map[string]any) bool {
	if _, named := line["health"]; named {
		return true
	}

	event, _ := line["event"].(string)

	return event == "health"
}

// Serve answers for the named service on the socket the journal gave it, until the returned stop is called. A
// journal that named no socket asks nothing, and then this costs the service nothing at all.
func Serve(service string) func() {
	return serveOn(os.Getenv(Socket), service)
}

// serveOn is Serve on a named socket, which is how a test gives it one.
func serveOn(path, service string) func() {
	if path == "" {
		return func() {}
	}

	os.MkdirAll(filepath.Dir(path), 0o755)
	os.Remove(path)

	listener, err := net.Listen("unix", path)
	if err != nil {
		return func() {}
	}

	os.Chmod(path, 0o600)
	go answer(listener, Answer(service))

	return func() { listener.Close() }
}

// answer says the same word to every question until the listener closes. The answer is written first and the
// question drained after: the journal writes before it reads, and a connection closed under its write would read
// as a service that failed rather than one that answered.
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
