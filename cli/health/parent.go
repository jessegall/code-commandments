package health

import (
	"os"
	"sync"
	"syscall"
	"time"
)

const (
	parentAskedEvery = 5 * time.Second
	stoppingWithin   = 10 * time.Second
)

var watchingParent sync.Once

func watchTheParent() {
	watchingParent.Do(func() {
		go untilOrphaned(os.Getppid, parentAskedEvery, stopThisProcess)
	})
}

func untilOrphaned(parent func() int, every time.Duration, stop func()) {
	started := parent()

	for parent() == started {
		time.Sleep(every)
	}

	stop()
}

func stopThisProcess() {
	if self, err := os.FindProcess(os.Getpid()); err == nil {
		self.Signal(syscall.SIGTERM)
	}

	time.Sleep(stoppingWithin)
	os.Exit(0)
}
