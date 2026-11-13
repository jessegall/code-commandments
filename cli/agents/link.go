package agents

import (
	"crypto/md5"
	"maps"
	"os"
	"path/filepath"
	"runtime"
	"strings"

	"github.com/jessegall/code-commandments/cli/folder"
	"github.com/jessegall/code-commandments/walk"
)

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

	return folder.Copy(target, link)
}

func alreadyPoints(link, target string) bool {
	info, err := os.Lstat(link)
	if err != nil {
		return false
	}

	if info.Mode()&os.ModeSymlink != 0 {
		resolved, err := walk.Resolved(link)
		real, realErr := walk.Resolved(target)

		return err == nil && realErr == nil && resolved == real
	}

	return info.IsDir() && maps.Equal(digests(link), digests(target))
}

func digests(dir string) map[string][16]byte {
	found := map[string][16]byte{}

	for _, path := range walk.AllFiles(dir) {
		contents, _ := os.ReadFile(path)
		relative, _ := filepath.Rel(dir, path)
		found[relative] = md5.Sum(contents)
	}

	return found
}

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
