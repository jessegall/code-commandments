//go:build !unix

package dashboard

import "os/exec"

func detach(*exec.Cmd) {}
