//go:build !unix

package atomic

import "os"

// umask is nothing where the platform has no creation mask.
func umask() os.FileMode {
	return 0
}
