package agents

import (
	"crypto/md5"
	"io/fs"
	"maps"
	"os"
	"path/filepath"
	"runtime"
	"strings"
)

// Point makes link reach target: a symbolic link, relative on POSIX and absolute on Windows (whose links
// resolve a relative target against the process's folder), or a copy where the filesystem has no links.
// False when the target does not exist or nothing could be put in place.
func Point(link, target string) bool {
	if _, err := os.Stat(target); err != nil {
		return false
	}

	if alreadyPoints(link, target) {
		return true
	}

	os.MkdirAll(filepath.Dir(link), 0o775)

	if err := os.RemoveAll(link); err != nil {
		return false
	}

	if os.Symlink(targetFor(link, target), link) == nil {
		return true
	}

	return copyTree(target, link)
}

func alreadyPoints(link, target string) bool {
	info, err := os.Lstat(link)
	if err != nil {
		return false
	}

	if info.Mode()&os.ModeSymlink != 0 {
		resolved, err := filepath.EvalSymlinks(link)
		real, realErr := filepath.EvalSymlinks(target)

		return err == nil && realErr == nil && resolved == real
	}

	return info.IsDir() && maps.Equal(digests(link), digests(target))
}

// digests are the md5 of every file under dir, by its path under dir.
func digests(dir string) map[string][16]byte {
	found := map[string][16]byte{}

	filepath.WalkDir(dir, func(path string, entry fs.DirEntry, err error) error {
		if err == nil && !entry.IsDir() {
			contents, _ := os.ReadFile(path)
			relative, _ := filepath.Rel(dir, path)
			found[relative] = md5.Sum(contents)
		}

		return nil
	})

	return found
}

// targetFor is the target as the link names it: relative to the link's folder, except on Windows.
func targetFor(link, target string) string {
	if runtime.GOOS == "windows" {
		return target
	}

	from := strings.Split(strings.Trim(filepath.Dir(link), "/"), "/")
	to := strings.Split(strings.Trim(target, "/"), "/")

	for len(from) > 0 && len(to) > 0 && from[0] == to[0] {
		from, to = from[1:], to[1:]
	}

	return strings.Join(append(slicesOf("..", len(from)), to...), "/")
}

func slicesOf(value string, count int) []string {
	values := make([]string, count)
	for i := range values {
		values[i] = value
	}

	return values
}

func copyTree(from, to string) bool {
	all := true

	err := filepath.WalkDir(from, func(path string, entry fs.DirEntry, err error) error {
		if err != nil {
			return err
		}

		relative, _ := filepath.Rel(from, path)
		target := filepath.Join(to, relative)

		if entry.IsDir() {
			return os.MkdirAll(target, 0o775)
		}

		contents, err := os.ReadFile(path)
		if err != nil || os.WriteFile(target, contents, 0o644) != nil {
			all = false
		}

		return nil
	})

	return err == nil && all
}
