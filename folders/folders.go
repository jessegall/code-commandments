// Package folders finds the folders of the installed version that the binary reads loose, so a release that changes only them runs on the binary it already has.
package folders

import (
	"os"
	"path/filepath"
)

func Installed(variable, name string) string {
	if named := os.Getenv(variable); named != "" {
		return named
	}
	running, err := os.Executable()
	if err != nil {
		return ""
	}

	return filepath.Join(filepath.Dir(running), "..", name)
}
