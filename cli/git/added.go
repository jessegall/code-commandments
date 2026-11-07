package git

import "strings"

func AddedVsHead(root string) map[string]bool {
	status := run(root, "status", "--porcelain=v2", "--no-renames", "--untracked-files=all", "-z")

	var paths []string
	for _, entry := range strings.Split(status, "\x00") {
		if path := addedPath(entry); path != "" {
			paths = append(paths, path)
		}
	}

	return pathSet(root, strings.Join(paths, "\n"))
}

func addedPath(entry string) string {
	if strings.HasPrefix(entry, "?") {
		return entry[min(2, len(entry)):]
	}

	if !strings.HasPrefix(entry, "1") {
		return ""
	}

	if !strings.HasPrefix(field(entry, 9, 1), "A") {
		return ""
	}

	return field(entry, 9, 8)
}
