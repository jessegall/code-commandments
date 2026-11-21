package engine

import (
	"strings"
	"unicode"
)

// FoldersBelow is the folders a relative path written as text names, its last segment left out unless a slash follows
// it, and whether the text reads as a path at all.
func FoldersBelow(written string) ([]string, bool) {
	if !readsAsPath(written) {
		return nil, false
	}
	segments := PathSegments(written)
	if !strings.HasSuffix(written, "/") {
		segments = segments[:max(len(segments)-1, 0)]
	}
	folder := CleanPath(segments)

	return folder, len(folder) > 0
}

func readsAsPath(text string) bool {
	if strings.Contains(text, "://") || strings.IndexFunc(text, unicode.IsSpace) >= 0 || strings.ContainsAny(text, "?#=%@:<>|*\"'") {
		return false
	}

	return strings.Contains(strings.Trim(text, "/"), "/") || strings.HasPrefix(text, "/") && len(text) > 1
}

func CleanPath(segments []string) []string {
	var clean []string
	for _, segment := range segments {
		switch segment {
		case "", ".":
		case "..":
			if len(clean) > 0 && clean[len(clean)-1] != ".." {
				clean = clean[:len(clean)-1]
			} else {
				clean = append(clean, segment)
			}
		default:
			clean = append(clean, segment)
		}
	}

	return clean
}
