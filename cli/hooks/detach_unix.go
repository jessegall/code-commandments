//go:build unix

package hooks

import (
	"os/exec"
	"syscall"
)

// detach starts the run in a session of its own, so it outlives the one that started it.
func detach(run *exec.Cmd) {
	run.SysProcAttr = &syscall.SysProcAttr{Setsid: true}
}
