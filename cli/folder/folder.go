// Package folder lists, orders, copies and deletes folders the way the tool always has: entries sorted
// by name, a link never walked through.
package folder

import (
	"io"
	"os"
	"path/filepath"
	"sort"
)

// Entries are the paths directly inside dir, dotfiles included, sorted by name; none when dir is not a
// folder.
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

// NewestFirst are the entries of dir, most recently modified first, ties in name order.
func NewestFirst(dir string) []string {
	entries := Entries(dir)

	sort.SliceStable(entries, func(i, j int) bool {
		return Modified(entries[i]) > Modified(entries[j])
	})

	return entries
}

// Modified is when the path last changed, in Unix seconds; 0 when it is not there.
func Modified(path string) int64 {
	info, err := os.Stat(path)
	if err != nil {
		return 0
	}

	return info.ModTime().Unix()
}

// Delete removes path whatever it is, a link as the link alone; true when it is gone.
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

// Copy copies the folder from into to, creating it; a link inside is copied as what it points at.
func Copy(from, to string) bool {
	if info, err := os.Stat(from); err != nil || !info.IsDir() {
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

// CopyFile copies one file's contents.
func CopyFile(from, to string) bool {
	source, err := os.Open(from)
	if err != nil {
		return false
	}

	defer source.Close()

	target, err := os.Create(to)
	if err != nil {
		return false
	}

	_, err = io.Copy(target, source)

	return target.Close() == nil && err == nil
}
