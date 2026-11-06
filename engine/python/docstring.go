package python

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/prose"
)

var (
	section      = regexp.MustCompile(`^(?:Args|Arguments|Parameters|Params|Other Parameters|Keyword Args|Returns?|Yields?|Raises|Attributes|Methods|Examples?|Notes?|See Also|Warnings?|Todo|References):?$`)
	underline    = regexp.MustCompile(`^-{3,}$`)
	versionNote  = regexp.MustCompile(`^([ \t]*)\.\. (?:versionadded|versionchanged|deprecated)::`)
	field        = regexp.MustCompile(`^:[\w ]+:`)
	argsHeading  = regexp.MustCompile(`^(?:Args|Arguments|Parameters|Params):$`)
	returnsHead  = regexp.MustCompile(`^Returns?:$`)
	paramField   = regexp.MustCompile(`^:(?:param(?:\s+\S+)?\s+(\w+)|type\s+(\w+)):\s*(.*)$`)
	returnField  = regexp.MustCompile(`^:(?:rtype:\s*\S.*|returns?:\s*)$`)
	returnType   = regexp.MustCompile(`^[\w.\[\], |]+:?$`)
	argsEntry    = regexp.MustCompile(`^(\w+)(?:\s*\([^)]*\))?:$|^(\w+) : \S`)
	crossRef     = regexp.MustCompile(":(?:py:)?(?:class|func|meth|attr|mod|obj|exc|data|const):`(?:[^`<]*<)?[~!.]?([\\w.]+)>?`")
	leadingSpace = " \t\n\r\x00\x0b"
)

func (n Node) Docstring() (string, bool) {
	body := n.ChildrenIn("body")
	if len(body) == 0 || body[0].Kind() != "Expr" {
		return "", false
	}

	return body[0].Child("value").Text()
}

func WithoutVersionNotes(text string) string {
	lines := strings.Split(text, "\n")
	var kept []string
	for at := 0; at < len(lines); at++ {
		note := versionNote.FindStringSubmatch(lines[at])
		if note == nil {
			kept = append(kept, lines[at])
			continue
		}
		kept = append(kept, "")
		for at+1 < len(lines) && (strings.Trim(lines[at+1], " \t") == "" || underNote(lines[at+1], note[1])) {
			at++
		}
	}

	return strings.Join(kept, "\n")
}

func underNote(line, indent string) bool {
	rest, ok := strings.CutPrefix(line, indent)

	return ok && rest != "" && (rest[0] == ' ' || rest[0] == '\t')
}

func ProseParagraphs(text string) int {
	lines := beforeFirstSection(strings.Split(WithoutVersionNotes(text), "\n"))
	base := baseIndent(lines)

	return prose.Paragraphs(lines, func(line string) bool {
		return !isMarkup(strings.TrimLeft(line, leadingSpace)) && indentOf(line) <= base
	})
}

func isMarkup(line string) bool {
	return strings.HasPrefix(line, ">>>") || strings.HasPrefix(line, "..") || field.MatchString(line)
}

func beforeFirstSection(lines []string) []string {
	for at, line := range lines {
		if !section.MatchString(strings.TrimSpace(line)) {
			continue
		}
		underlined := at+1 < len(lines) && underline.MatchString(strings.TrimSpace(lines[at+1]))
		if strings.HasSuffix(strings.TrimSpace(line), ":") || underlined {
			return lines[:at]
		}
	}

	return lines
}

func baseIndent(lines []string) int {
	base := -1
	for _, line := range lines[min(1, len(lines)):] {
		if strings.TrimSpace(line) != "" && (base < 0 || indentOf(line) < base) {
			base = indentOf(line)
		}
	}

	return max(base, 0)
}

func indentOf(line string) int {
	return len(line) - len(strings.TrimLeft(line, leadingSpace))
}

func OnlyRestates(text string, annotated []string, returnAnnotated bool) bool {
	var lines []string
	for _, line := range strings.Split(text, "\n") {
		if trimmed := strings.TrimSpace(line); trimmed != "" {
			lines = append(lines, trimmed)
		}
	}
	current, restated := "", 0
	for at, line := range lines {
		underlined := at+1 < len(lines) && underline.MatchString(lines[at+1])
		switch {
		case underline.MatchString(line):
			continue
		case argsHeading.MatchString(line) || (underlined && (line == "Parameters" || line == "Params")):
			current = "args"
			continue
		case returnsHead.MatchString(line) || (underlined && line == "Returns"):
			current = "returns"
			continue
		}
		if !restates(line, current, annotated, returnAnnotated) {
			return false
		}
		restated++
	}

	return restated > 0
}

func restates(line, current string, annotated []string, returnAnnotated bool) bool {
	if entry := paramField.FindStringSubmatch(line); entry != nil {
		isParam := entry[1] != ""
		name := entry[2]
		if isParam {
			name = entry[1]
		}

		return slices.Contains(annotated, name) && (!isParam || entry[3] == "")
	}
	if returnField.MatchString(line) {
		return returnAnnotated
	}
	if current == "returns" {
		return returnAnnotated && returnType.MatchString(line)
	}
	entry := argsEntry.FindStringSubmatch(line)
	if current != "args" || entry == nil {
		return false
	}
	name := entry[2]
	if entry[1] != "" {
		name = entry[1]
	}

	return slices.Contains(annotated, name)
}

func References(text string) []string {
	var found []string
	for _, reference := range crossRef.FindAllStringSubmatch(text, -1) {
		if strings.Contains(reference[1], ".") && !slices.Contains(found, reference[1]) {
			found = append(found, reference[1])
		}
	}

	return found
}
