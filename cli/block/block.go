// Package block keeps a generated block inside a document someone else owns: the text between a BEGIN and an END marker is the tool's to rewrite, and everything around it is left as it was.
package block

import (
	"fmt"
	"strings"
)

const open = "<!-- BEGIN: "

// Malformed is a block whose markers cannot be trusted to bound it.
type Malformed struct {
	Name   string
	Reason string
}

func (e *Malformed) Error() string {
	return fmt.Sprintf("the `%s` block cannot be placed: %s. Fix the markers by hand — writing over them would risk the text between.", e.Name, e.Reason)
}

func Replace(document, name, content string) (string, bool, error) {
	lines := strings.Split(document, "\n")

	begin, end, found, err := bounds(lines, name)
	if err != nil {
		return "", false, err
	}

	if !found {
		return document, false, nil
	}

	if !strings.HasSuffix(content, "\n") {
		content += "\n"
	}

	return strings.Join(lines[:begin+1], "\n") + content + strings.Join(lines[end:], "\n"), true, nil
}

func Remove(document, name string) (string, bool, error) {
	lines := strings.Split(document, "\n")

	begin, end, found, err := bounds(lines, name)
	if err != nil {
		return "", false, err
	}

	if !found {
		return document, false, nil
	}

	above := strings.TrimRight(strings.Join(lines[:begin], "\n"), "\n")
	below := strings.TrimLeft(strings.Join(lines[end+1:], "\n"), "\n")

	return strings.Trim(above+"\n\n"+below, "\n") + "\n", true, nil
}

func bounds(lines []string, name string) (int, int, bool, error) {
	begins := linesMatching(lines, func(line string) bool {
		return strings.HasPrefix(line, open+name+" ") && strings.HasSuffix(line, "-->")
	})
	ends := linesMatching(lines, func(line string) bool { return line == End(name) })

	switch {
	case len(begins) == 0 && len(ends) == 0:
		return 0, 0, false, nil
	case len(begins) > 1 || len(ends) > 1:
		return 0, 0, false, &Malformed{name, "the document carries more than one of them"}
	case len(begins) == 0:
		return 0, 0, false, &Malformed{name, "it has an END marker with no BEGIN"}
	case len(ends) == 0:
		return 0, 0, false, &Malformed{name, "it has a BEGIN marker with no END"}
	case ends[0] < begins[0]:
		return 0, 0, false, &Malformed{name, "its END marker stands above its BEGIN"}
	}

	return begins[0], ends[0], true, nil
}

func Begin(name, command string) string {
	return open + name + " (auto-generated, run `" + command + "`) -->"
}

func End(name string) string {
	return "<!-- END: " + name + " -->"
}

func linesMatching(lines []string, matches func(string) bool) []int {
	var found []int

	for i, line := range lines {
		if matches(strings.Trim(line, " \t\n\r\x00\x0B")) {
			found = append(found, i)
		}
	}

	return found
}
