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
