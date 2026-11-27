// Package skills carries the curriculum the binary publishes into a project: the generated skills under commandments/ and every hand-written skill beside them, each a folder holding its SKILL.md.
package skills

import (
	"embed"
	"io/fs"
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/folders"
)

//go:embed *
var embedded embed.FS

var Files = In(folders.Installed("COMMANDMENTS_SHIPPED_SKILLS", "skills"))

func In(folder string) fs.FS {
	if info, err := os.Stat(filepath.Join(folder, "commandments")); folder != "" && err == nil && info.IsDir() {
		return os.DirFS(folder)
	}

	return embedded
}
