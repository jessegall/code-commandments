package report

import (
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"strings"
)

const (
	// before is how many lines a snippet shows above the line it is about.
	before = 3

	// after is how many lines it shows below a single line; a range shows before as many.
	after = 24
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

// Snippet is the file's code around the reference, numbered, the referenced lines marked and every secret
// masked, as a fenced block; false when the file cannot be read or is empty.
func Snippet(ref Reference) (string, bool) {
	raw, err := os.ReadFile(ref.Path)
	if err != nil || len(raw) == 0 {
		return "", false
	}

	lines := strings.Split(strings.TrimSuffix(string(raw), "\n"), "\n")
	from := max(1, ref.Start)
	to := max(from, ref.End, from)
	start := max(1, from-before)
	below := after

	if ref.End != 0 {
		below = before
	}

	end := min(len(lines), to+below)
	width := len(strconv.Itoa(end))
	var rows []string

	for n := start; n <= end; n++ {
		marker := " "
		if ref.Start != 0 && n >= from && n <= to {
			marker = "→"
		}

		rows = append(rows, fmt.Sprintf("%s %*d  %s", marker, width, n, Redact(strings.TrimSuffix(lines[n-1], "\r"))))
	}

	return "**Code** (`" + where(ref) + "`):\n\n```" + fence(ref.Path) + "\n" + strings.Join(rows, "\n") + "\n```\n", true
}

func where(ref Reference) string {
	switch {
	case ref.Start == 0:
		return ref.Path
	case ref.End > ref.Start:
		return ref.Path + ":" + strconv.Itoa(ref.Start) + "-" + strconv.Itoa(ref.End)
	default:
		return ref.Path + ":" + strconv.Itoa(ref.Start)
	}
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
