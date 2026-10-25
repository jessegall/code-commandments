// Package golang holds Go's own knowledge for the engine, as php and csharp hold theirs: what only a reader of
// Go can say about a Go tree.
package golang

import (
	"bytes"
	"regexp"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.Freezes(contract.Go, isGenerated, func(_ string, source []byte) bool {
		return bytes.Contains(source, []byte("Code generated"))
	})
}

// generatedLine is the line Go's own convention says a generated file carries, as go.dev/s/generatedcode spells
// it: a line of its own, before the package clause, that no tool is to edit past.
var generatedLine = regexp.MustCompile(`^// Code generated .* DO NOT EDIT\.$`)

// isGenerated says whether the file is one a generator writes. Such a file is read, so every type and every call
// through it still resolves, and reported on never: a finding in it is a finding nobody can fix, since the next
// run of the generator writes the file again.
//
// Go states the marker itself rather than leaving each project to invent one, and protoc, mockgen, stringer and
// the standard library's own generators all write it. The convention is the line's own text and its place before
// the package clause, so a comment deeper in a file that happens to say the words is no marker.
func isGenerated(file *engine.File) bool {
	source, err := file.Source()
	if err != nil {
		return false
	}

	for _, line := range strings.Split(string(source), "\n") {
		trimmed := strings.TrimRight(line, "\r")
		if generatedLine.MatchString(trimmed) {
			return true
		}
		if isCode(trimmed) {
			return false
		}
	}

	return false
}

// isCode says whether the line is code rather than a comment or a blank, which is where a generated file's
// marker must stand before.
func isCode(line string) bool {
	opened := strings.TrimSpace(line)

	return opened != "" && !strings.HasPrefix(opened, "//") && !strings.HasPrefix(opened, "/*") && !strings.HasPrefix(opened, "*")
}
