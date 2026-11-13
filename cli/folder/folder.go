// Package folder lists, orders, copies and deletes folders the way the tool always has: entries sorted by name, a link never walked through.
package folder

import (
	"os"
	"path/filepath"
	"sort"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/walk"
)

func Entries(dir string) []string {
	names, err := os.ReadDir(dir)
	if err != nil {
		return nil
	}

	entries := make([]string, 0, len(names))

	for _, name := range names {
		entries = append(entries, dir+"/"+name.Name())
	}

	sort.Strings(entries)

	return entries
}

func NewestFirst(dir string) []string {
	entries := Entries(dir)

	sort.SliceStable(entries, func(i, j int) bool {
		return Modified(entries[i]) > Modified(entries[j])
	})

	return entries
}

func Modified(path string) int64 {
	info, err := os.Stat(path)
	if err != nil {
		return 0
	}

	return info.ModTime().Unix()
}

func Delete(path string) bool {
	info, err := os.Lstat(path)

	switch {
	case err != nil:
		return true
	case info.Mode()&os.ModeSymlink != 0 || !info.IsDir():
		return os.Remove(path) == nil
	}

	emptied := true

	for _, entry := range Entries(path) {
		emptied = Delete(entry) && emptied
	}

	return os.Remove(path) == nil && emptied
}

func Copy(from, to string) bool {
	if !walk.IsFolder(from) {
		return false
	}

	if err := os.MkdirAll(to, 0o775); err != nil {
		return false
	}

	copied := true

	for _, entry := range Entries(from) {
		target := to + "/" + filepath.Base(entry)
		info, err := os.Lstat(entry)

		if err == nil && info.IsDir() {
			copied = Copy(entry, target) && copied

			continue
		}

		copied = CopyFile(entry, target) && copied
	}

	return copied
}

func CopyFile(from, to string) bool {
	contents, err := os.ReadFile(from)

	return err == nil && atomic.Write(to, string(contents)) == nil
}
