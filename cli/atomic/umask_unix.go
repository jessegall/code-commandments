//go:build unix

package atomic

import (
	"os"
	"syscall"
)

// umask is the process's file-creation mask, read by setting it and putting it straight back.
func umask() os.FileMode {
	mask := syscall.Umask(0)
	syscall.Umask(mask)

	return os.FileMode(mask)
}
