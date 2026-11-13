package source

import "github.com/jessegall/code-commandments/walk"

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

// FilesIn is every file of the language under the path, by each extension the language is written in.
func FilesIn(path string, language Language, excluded Excluded) []string {
	return walk.Files(path, excluded, func(file string) bool { return OfFile(file) == language })
}
