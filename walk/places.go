package walk

import (
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"
)

func Same(one, two string) bool {
	return real(one) == real(two)
}

func Inside(folder, path string) (string, bool) {
	under, err := filepath.Rel(real(folder), real(path))
	if err != nil || strings.HasPrefix(under, "..") {
		return "", false
	}

	return under, true
}

func Counterpart(checkout, project, file string) string {
	if real(checkout) == real(project) {
		return file
	}

	under, inside := Inside(checkout, file)
	if !inside {
		return file
	}

	return filepath.Join(real(project), under)
}

func Mark(path string) string {
	info, err := os.Stat(path)
	if err != nil {
		return ""
	}

	return info.ModTime().UTC().Format(time.RFC3339Nano) + "\x00" + strconv.FormatInt(info.Size(), 10)
}

func real(path string) string {
	if resolved, err := filepath.EvalSymlinks(path); err == nil {
		return resolved
	}

	return path
}
