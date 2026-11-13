package health

import (
	"fmt"
	"os"
	"path/filepath"
	"sync"
	"syscall"
	"time"

	"github.com/jessegall/code-commandments/walk"
)

const (
	parentAskedEvery = 5 * time.Second
	stoppingWithin   = 10 * time.Second
	noParentLeft     = 1
	containerMark    = "/.dockerenv"
	pluginDir        = "JOURNAL_PLUGIN_DIR"
	pluginMark       = ".journal-plugin"
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

		if folder := pluginFolder(); folder != "" {
			go untilRemoved(folder, parentAskedEvery, stopThisProcess)
		}
	})
}

func untilRemoved(folder string, every time.Duration, stop func()) {
	for {
		if !walk.Exists(folder) {
			break
		}

		time.Sleep(every)
	}

	stop()
}

func pluginFolder() string {
	if folder := os.Getenv(pluginDir); folder != "" {
		return folder
	}

	self, err := os.Executable()
	if err != nil {
		return ""
	}

	folder := filepath.Dir(filepath.Dir(self))
	if !walk.Exists(filepath.Join(folder, pluginMark)) {
		return ""
	}

	return folder
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
	return walk.Exists(containerMark)
}

func stopThisProcess() {
	fmt.Fprintln(os.Stderr, "this service stops: its parent or its plugin folder is gone")

	if self, err := os.FindProcess(os.Getpid()); err == nil {
		self.Signal(syscall.SIGTERM)
	}

	time.Sleep(stoppingWithin)
	os.Exit(0)
}
