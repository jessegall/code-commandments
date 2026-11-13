package walk

import (
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"
)

func Same(one, two string) bool {
	return Real(one) == Real(two)
}

// Within says whether the path is the folder or lies under it, both as written: no link is resolved, so it is for
// paths that are already real. Inside resolves both first.
func Within(folder, path string) bool {
	folder = strings.TrimRight(folder, "/"+string(filepath.Separator))

	return path == folder || strings.HasPrefix(path, folder+"/") || strings.HasPrefix(path, folder+string(filepath.Separator))
}

func Inside(folder, path string) (string, bool) {
	under, err := filepath.Rel(Real(folder), Real(path))
	if err != nil || strings.HasPrefix(under, "..") {
		return "", false
	}

	return under, true
}

func Counterpart(checkout, project, file string) string {
	if Real(checkout) == Real(project) {
		return file
	}

	under, inside := Inside(checkout, file)
	if !inside {
		return file
	}

	return filepath.Join(Real(project), under)
}

func Mark(path string) string {
	info, err := os.Stat(path)
	if err != nil {
		return ""
	}

	return info.ModTime().UTC().Format(time.RFC3339Nano) + "\x00" + strconv.FormatInt(info.Size(), 10)
}
