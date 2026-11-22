package engine

import (
	"strings"
	"unicode"
)

// FoldersBelow is the folders a relative path written as text names, its last segment left out when it names a file
// (it has an extension and no slash follows it), and whether the text reads as a path at all.
func FoldersBelow(written string) ([]string, bool) {
	if !readsAsPath(written) {
		return nil, false
	}
	segments := PathSegments(written)
	if last := len(segments) - 1; last >= 0 && !strings.HasSuffix(written, "/") && strings.Contains(segments[last], ".") {
		segments = segments[:last]
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
