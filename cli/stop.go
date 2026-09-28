package cli

import (
	"os"
	"os/signal"
	"syscall"
)

// StopSignals is where the session's stop arrives: a service with nothing to serve waits on it rather than ending, so
// the journal does not start it again.
func StopSignals() <-chan os.Signal {
	stopped := make(chan os.Signal, 1)
	signal.Notify(stopped, syscall.SIGTERM, os.Interrupt)

	return stopped
}
