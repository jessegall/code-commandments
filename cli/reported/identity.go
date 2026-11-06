// Package reported holds what a project has already reported to the tool's own repository: what identifies the sin a report was filed about, and the record of the reports themselves.
package reported

import (
	"crypto/sha1"
	"fmt"
	"os"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli/source"
)

func Identity(rule, root, file string, line int) (id string, read bool) {
	text, read := flaggedText(file, line)
	if !read {
		text = "line " + strconv.Itoa(line)
	}

	return fmt.Sprintf("%x", sha1.Sum([]byte(rule+"\x00"+source.Relative(root, file)+"\x00"+text))), read
}

func flaggedText(file string, line int) (text string, read bool) {
	held, err := os.ReadFile(file)
	if err != nil {
		return "", false
	}

	lines := strings.SplitAfter(string(held), "\n")
	if line < 1 || line > len(lines) {
		return "", false
	}

	if text = strings.TrimSpace(lines[line-1]); text == "" {
		return "", false
	}

	return text, true
}
