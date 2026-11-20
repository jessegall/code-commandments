//go:build unix

package dashboard

import (
	"os/exec"
	"syscall"
)

func detach(run *exec.Cmd) {
	run.SysProcAttr = &syscall.SysProcAttr{Setsid: true}
}
