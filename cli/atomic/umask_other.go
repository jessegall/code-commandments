//go:build !unix

package atomic

import "os"

func umask() os.FileMode {
	return 0
}
