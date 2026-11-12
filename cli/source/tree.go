package source

import (
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/walk"
)

type Excluded = walk.Excluded

var (
	Under        = walk.Under
	Repositories = walk.Repositories
	Reaches      = walk.Reaches
	IsWorktree   = walk.IsWorktree
	InTree       = walk.InTree
	InWorktree   = walk.InWorktree
)

func Sources(path string, excluded Excluded) []string {
	return walk.Files(path, excluded, Judges)
}

func FilesIn(path, extension string, excluded Excluded) []string {
	return walk.Files(path, excluded, func(file string) bool {
		return strings.TrimPrefix(filepath.Ext(file), ".") == extension
	})
}
