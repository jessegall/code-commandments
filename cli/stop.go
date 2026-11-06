package cli

import (
	"os"
	"os/signal"
	"syscall"
)

func StopSignals() <-chan os.Signal {
	stopped := make(chan os.Signal, 1)
	signal.Notify(stopped, syscall.SIGTERM, os.Interrupt)

	return stopped
}
