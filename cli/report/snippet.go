package report

import (
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"strings"
)

// Reference is the code a report is about: a file, and a line or a range of lines in it.
type Reference struct {
	Path  string
	Start int
	End   int
}

// Label is the reference as written: path, path:line or path:start-end.
func (r Reference) Label() string {
	switch {
	case r.Start == 0:
		return r.Path
	case r.End == 0:
		return r.Path + ":" + strconv.Itoa(r.Start)
	default:
		return r.Path + ":" + strconv.Itoa(r.Start) + "-" + strconv.Itoa(r.End)
	}
}

// Shown is the reference as an issue names it: the file's own name and its lines, never the folders that would map
// out the reporter's project.
func (r Reference) Shown() string {
	return Reference{filepath.Base(r.Path), r.Start, r.End}.Label()
}

// ParseReference reads `path`, `path:line` or `path:start-end`; a suffix that is no line keeps the whole
// value as the path. False for a blank value.
func ParseReference(value string) (Reference, bool) {
	value = strings.Trim(value, " \t\n\r\x00\x0B")
	if value == "" {
		return Reference{}, false
	}

	colon := strings.LastIndex(value, ":")
	if colon < 0 {
		return Reference{Path: value}, true
	}

	start, end, isSpan := span(value[colon+1:])
	if !isSpan {
		return Reference{Path: value}, true
	}

	return Reference{value[:colon], start, end}, true
}

func span(spec string) (int, int, bool) {
	from, to, ranged := strings.Cut(spec, "-")

	if !ranged {
		start, isLine := digits(spec)

		return start, 0, isLine
	}

	start, isStart := digits(from)
	end, isEnd := digits(to)

	return start, end, isStart && isEnd
}

func digits(text string) (int, bool) {
	if text == "" || strings.Trim(text, "0123456789") != "" {
		return 0, false
	}

	number, err := strconv.Atoi(text)

	return number, err == nil
}

// Snippet is the referenced lines alone, numbered, with every secret masked, as a fenced block: never the code
// around them, and nothing for a reference to a whole file; false when there are no lines to show.
func Snippet(ref Reference) (string, bool) {
	raw, err := os.ReadFile(ref.Path)
	if err != nil || len(raw) == 0 || ref.Start == 0 {
		return "", false
	}

	lines := strings.Split(strings.TrimSuffix(string(raw), "\n"), "\n")
	from := ref.Start
	to := min(len(lines), max(from, ref.End))
	if from > len(lines) {
		return "", false
	}

	width := len(strconv.Itoa(to))
	var rows []string

	for n := from; n <= to; n++ {
		rows = append(rows, fmt.Sprintf("%*d  %s", width, n, Redact(strings.TrimSuffix(lines[n-1], "\r"))))
	}

	return "**Code** (`" + ref.Shown() + "`):\n\n```" + fence(ref.Path) + "\n" + strings.Join(rows, "\n") + "\n```\n", true
}

func fence(path string) string {
	switch strings.ToLower(strings.TrimPrefix(filepath.Ext(path), ".")) {
	case "php":
		return "php"
	case "vue":
		return "vue"
	case "ts", "mts", "cts":
		return "ts"
	case "js", "mjs", "cjs":
		return "js"
	default:
		return ""
	}
}
