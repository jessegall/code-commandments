package source

import (
	"strings"

	"github.com/jessegall/code-commandments/walk"
)

func Relative(root, path string) string {
	if real, err := walk.Resolved(root); err == nil {
		root = real
	}

	file, line, located := strings.Cut(path, ":")
	if real, err := walk.Resolved(file); err == nil {
		file = real
	}

	inside, under := strings.CutPrefix(file, strings.TrimRight(root, "/")+"/")
	if !under {
		return path
	}

	if located {
		return inside + ":" + line
	}

	return inside
}
