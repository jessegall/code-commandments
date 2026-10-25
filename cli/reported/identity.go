// Package reported holds what a project has already reported to the tool's own repository: what identifies the sin
// a report was filed about, and the record of the reports themselves.
package reported

import (
	"crypto/sha1"
	"fmt"
	"os"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli/source"
)

// Identity names one finding in one place: the rule that found it, the file as the project names it, and the text
// of the line it stands on. The text and not the line's number, because an edit above a finding moves its line and
// leaves the finding where it was; and hashed, never kept, because the record is committed to the project while the
// issue it names is public.
//
// The RULE and not the sin it finds, for two reasons. A report disputes one rule's claim about a line, and another
// rule's claim about the same line is a claim of its own that nobody has answered. And a rule's name is what the
// reporter has in hand, so nothing has to resolve it: two engines can name a detector alike, and resolving such a
// name to a sin would pick whichever engine answered first and key the row to a sin the judged file never meets.
//
// read is false where the line's text could not be read at all: an unreadable file, a line past the end, a blank
// line. A report must refuse to key on that, since every such sin in that file would otherwise share one identity.
// The identity still answers, keyed by the line's number instead, which tells two marks of one run apart but moves
// with the next edit above them.
func Identity(rule, root, file string, line int) (id string, read bool) {
	text, read := flaggedText(file, line)
	if !read {
		text = "line " + strconv.Itoa(line)
	}

	return fmt.Sprintf("%x", sha1.Sum([]byte(rule+"\x00"+source.Relative(root, file)+"\x00"+text))), read
}

// flaggedText is the text of the line, trimmed; read is false where the file or the line is not there to read, or
// the line holds nothing but space.
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
