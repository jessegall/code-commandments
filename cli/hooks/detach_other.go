//go:build !unix

package hooks

import "os/exec"

// detach leaves the run as it is where there are no sessions to start it in.
func detach(run *exec.Cmd) {}
