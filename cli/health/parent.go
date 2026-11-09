package health

import (
	"fmt"
	"os"
	"sync"
	"syscall"
	"time"
)

const (
	parentAskedEvery = 5 * time.Second
	stoppingWithin   = 10 * time.Second
	noParentLeft     = 1
	containerMark    = "/.dockerenv"
)

var watchingParent sync.Once

func watchTheParent() {
	watchingParent.Do(func() {
		go func() {
			if hasNoParent(os.Getppid(), inAContainer()) {
				stopThisProcess()
			}

			untilOrphaned(os.Getppid, parentAskedEvery, stopThisProcess)
		}()
	})
}

func untilOrphaned(parent func() int, every time.Duration, stop func()) {
	started := parent()

	for parent() == started {
		time.Sleep(every)
	}

	stop()
}

func hasNoParent(parent int, contained bool) bool {
	return parent == noParentLeft && !contained
}

func inAContainer() bool {
	_, err := os.Stat(containerMark)

	return err == nil
}

func stopThisProcess() {
	fmt.Fprintln(os.Stderr, "this service stops: its parent is gone")

	if self, err := os.FindProcess(os.Getpid()); err == nil {
		self.Signal(syscall.SIGTERM)
	}

	time.Sleep(stoppingWithin)
	os.Exit(0)
}
