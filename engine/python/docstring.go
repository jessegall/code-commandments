package python

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/prose"
)

var (
	// section is a heading the Google and NumPy docstring styles open a section with.
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

// Docstring is the docstring of a module, class or def: the string its body opens with, decoded.
func (n Node) Docstring() (string, bool) {
	body := n.ChildrenIn("body")
	if len(body) == 0 || body[0].Kind() != "Expr" {
		return "", false
	}

	return body[0].Child("value").Text()
}

// WithoutVersionNotes is the docstring without its Sphinx version notes: each `.. versionadded::`,
// `.. versionchanged::` or `.. deprecated::` directive and the lines indented under it. Those record a public API's
// history for its users on purpose; the rest of the docstring describes the code.
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

// underNote says whether the line is indented past the note's own indentation.
func underNote(line, indent string) bool {
	rest, ok := strings.CutPrefix(line, indent)

	return ok && rest != "" && (rest[0] == ' ' || rest[0] == '\t')
}

// ProseParagraphs is how many paragraphs of prose the docstring holds before its first section, a Google `Args:`
// or a NumPy `Parameters` over dashes, with its version notes, markup lines and indented blocks set aside.
func ProseParagraphs(text string) int {
	lines := beforeFirstSection(strings.Split(WithoutVersionNotes(text), "\n"))
	base := baseIndent(lines)

	return prose.Paragraphs(lines, func(line string) bool {
		return !isMarkup(strings.TrimLeft(line, leadingSpace)) && indentOf(line) <= base
	})
}

// isMarkup says whether the line is markup rather than prose: a doctest `>>>`, a reST field (`:param app:`) or a
// directive (`..`).
func isMarkup(line string) bool {
	return strings.HasPrefix(line, ">>>") || strings.HasPrefix(line, "..") || field.MatchString(line)
}

// beforeFirstSection is the lines up to the one that opens their first section; all of them when there is none.
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

// baseIndent is the indentation the body of the lines is written at: the first line sits right after the quotes.
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

// OnlyRestates says whether the docstring says nothing but what the signature already says: no summary, and every
// entry a bare restatement of an annotated parameter or, when the return is annotated, of the return. `Args:`
// entries with no description, a bare `Returns:` type, NumPy `name : type` lines, Sphinx `:param x:`, `:type x:`
// and `:rtype:` fields. A description, a type for an unannotated parameter, or any other section earns its keep.
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

// restates says whether the line, read in its section, is a bare restatement of an annotated parameter or return.
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

// References is the dotted names the docstring's Sphinx cross-references point at: `shop.cart.Cart` from
// :class:`~shop.cart.Cart` or :func:`total <shop.cart.total>`, leaving out a bare name, which resolves against
// wherever Sphinx is told to look.
func References(text string) []string {
	var found []string
	for _, reference := range crossRef.FindAllStringSubmatch(text, -1) {
		if strings.Contains(reference[1], ".") && !slices.Contains(found, reference[1]) {
			found = append(found, reference[1])
		}
	}

	return found
}
