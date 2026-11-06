//go:build unix

package hooks

import (
	"os/exec"
	"syscall"
)

func detach(run *exec.Cmd) {
	run.SysProcAttr = &syscall.SysProcAttr{Setsid: true}
}
