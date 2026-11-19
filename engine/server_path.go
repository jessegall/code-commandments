package engine

import "strings"

const AnySegment = "\x00"

func PathSegments(path string) []string {
	var segments []string
	for _, segment := range strings.Split(path, "/") {
		if segment != "" {
			segments = append(segments, segment)
		}
	}

	return segments
}

func WrittenPath(text string) []string {
	if !strings.HasPrefix(text, "/") || strings.HasPrefix(text, "//") {
		return nil
	}

	return PathSegments(text)
}

func PathFits(route, written []string) bool {
	if len(route) != len(written) {
		return false
	}
	for at, segment := range route {
		parameter := strings.HasPrefix(segment, "{") && strings.HasSuffix(segment, "}")
		if !parameter && segment != written[at] {
			return false
		}
	}

	return true
}
