//go:build !unix

package hooks

import "os/exec"

func detach(run *exec.Cmd) {}
