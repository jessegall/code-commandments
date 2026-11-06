// Package golang holds Go's own knowledge for the engine, as php and csharp hold theirs: what only a reader of Go can say about a Go tree.
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

var generatedLine = regexp.MustCompile(`^// Code generated .* DO NOT EDIT\.$`)

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

func isCode(line string) bool {
	opened := strings.TrimSpace(line)

	return opened != "" && !strings.HasPrefix(opened, "//") && !strings.HasPrefix(opened, "/*") && !strings.HasPrefix(opened, "*")
}
