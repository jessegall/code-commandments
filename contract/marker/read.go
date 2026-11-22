package marker

import (
	"bytes"
	"fmt"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// Marker is one directive of a source file with the code line it speaks about and the comment that holds it.
type Marker struct {
	Directive
	Path          string
	Line          int
	CommentColumn int
	Start         int
	End           int
}

func Read(language contract.Language, path string, source []byte) ([]Marker, []error) {
	known := contract.Items()
	lines := bytes.Split(source, []byte{'\n'})
	var markers []Marker
	var faults []error
	var stack []Marker
	for _, comment := range Comments(language, source) {
		for offset, text := range strings.Split(comment.Text, "\n") {
			text = strings.TrimSpace(strings.TrimLeft(strings.TrimSpace(text), "*"))
			if !strings.HasPrefix(text, "@contract") && !strings.HasPrefix(text, "@label") {
				continue
			}
			line := comment.Line + offset
			directive, err := Parse(text)
			if err == nil {
				err = keyFault(directive, known)
			}
			if err != nil {
				faults = append(faults, fmt.Errorf("%s:%d: %v", path, line, err))

				continue
			}
			marker := Marker{Directive: directive, Path: path, Line: line, CommentColumn: comment.Column, Start: comment.Start, End: comment.End}
			if comment.Trailing {
				markers = append(markers, marker)

				continue
			}
			stack = append(stack, marker)
		}
		if comment.Trailing || len(stack) == 0 {
			continue
		}
		last := stack[len(stack)-1].Line
		if next := nextCodeLine(lines, last, language); next > 0 {
			for _, held := range stack {
				held.Line = next
				markers = append(markers, held)
			}
		} else {
			for _, held := range stack {
				faults = append(faults, fmt.Errorf("%s:%d: the marker stands before no code: a blank line or the end of the file follows it", path, held.Line))
			}
		}
		stack = nil
	}

	return markers, faults
}

func keyFault(directive Directive, known []string) error {
	if directive.Key == "" {
		return nil
	}
	if _, found := slices.BinarySearch(known, directive.Key); !found {
		return fmt.Errorf("%s is no contract item", directive.Key)
	}
	for _, target := range directive.Targets {
		if target.Path != "" && !strings.HasPrefix(directive.Key, "promise/") {
			return fmt.Errorf("%s points into another source (%s#%s), which only a promise may", directive.Key, target.Path, target.Label)
		}
	}

	return nil
}

func nextCodeLine(lines [][]byte, after int, language contract.Language) int {
	for line := after + 1; line <= len(lines); line++ {
		text := bytes.TrimSpace(lines[line-1])
		switch {
		case len(text) == 0:
			return 0
		case onlyComment(text, language):
			continue
		default:
			return line
		}
	}

	return 0
}

func onlyComment(text []byte, language contract.Language) bool {
	languages := []contract.Language{language}
	for _, region := range contract.LexiconOf(language).Regions {
		languages = append(languages, region.Language)
	}

	return slices.ContainsFunc(languages, func(each contract.Language) bool {
		found := Comments(each, text)

		return len(found) > 0 && found[0].Start == 0 && found[len(found)-1].End == len(text)
	})
}
