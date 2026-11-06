//go:build unix

package atomic

import (
	"os"
	"syscall"
)

func umask() os.FileMode {
	mask := syscall.Umask(0)
	syscall.Umask(mask)

	return os.FileMode(mask)
}
