package samples

import (
	"os"
	"path/filepath"
)

type Folder string

func (f Folder) Entries() int {
	// @sin FolderWalkOutsideTheFunnelDetector
	found, _ := os.ReadDir(string(f))

	return len(found)
}

func (f Folder) Child(name string) string {
	// @righteous FolderWalkOutsideTheFunnelDetector
	return filepath.Join(string(f), name)
}
