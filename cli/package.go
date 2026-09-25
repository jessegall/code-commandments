package cli

import (
	"path/filepath"
	"runtime"
)

// PackageRoot is the folder the tool's own sources live in: its skills, bridges and stubs.
func PackageRoot() string {
	_, file, _, _ := runtime.Caller(0)

	return filepath.Dir(filepath.Dir(file))
}
