package php

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/prose"
)

// DocblockIsInline says whether a docblock with content opens or closes on a content line rather than on a line of
// its own.
func DocblockIsInline(text string) bool {
	if len(docblockContent(text)) == 0 {
		return false
	}
	lines := prose.Lines(text)

	return prose.Trim(lines[0]) != "/**" || prose.Trim(lines[len(lines)-1]) != "*/"
}

// docblockContent is a docblock's lines with the delimiters and each line's star taken off, blank lines around
// them dropped.
func docblockContent(text string) []string {
	body := prose.Trim(text)
	body = strings.TrimPrefix(body, "/**")
	body = strings.TrimSuffix(body, "*/")
	var lines []string
	for _, line := range prose.Lines(body) {
		line = prose.Trim(line)
		if strings.HasPrefix(line, "*") {
			line = strings.TrimLeft(line[1:], " \t\n\r\x00\x0B")
		}
		lines = append(lines, strings.TrimRight(line, " \t\n\r\x00\x0B"))
	}
	for len(lines) > 0 && lines[0] == "" {
		lines = lines[1:]
	}
	for len(lines) > 0 && lines[len(lines)-1] == "" {
		lines = lines[:len(lines)-1]
	}

	return lines
}

// DocLines is a doc comment's lines, each trimmed of its delimiters and stars as a line-by-line reader sees them.
func DocLines(text string) []string {
	var lines []string
	for _, line := range prose.Lines(text) {
		lines = append(lines, prose.Trim(strings.TrimLeft(prose.Trim(line), "/*")))
	}

	return lines
}

// DocParagraphs is how many prose paragraphs a doc comment holds, tag lines aside.
func DocParagraphs(text string) int {
	return prose.Paragraphs(DocLines(text), func(line string) bool { return !strings.HasPrefix(line, "@") })
}

var docReference = regexp.MustCompile(`\{@(?:see|link)\s+\\?([A-Za-z_][\w\\]*\\[\w\\]+)`)

// DocReferences is every class a doc comment points at with {@see} or {@link}, once each, in order.
func DocReferences(text string) []string {
	var references []string
	for _, match := range docReference.FindAllStringSubmatch(text, -1) {
		if !slices.Contains(references, match[1]) {
			references = append(references, match[1])
		}
	}

	return references
}

// DocblockCanonical is the docblock in canonical form at indent: its content verbatim and in order, a `*` line each,
// between delimiters that stand alone.
func DocblockCanonical(text, indent string) string {
	return docblockOf(docblockContent(text), indent)
}

// DocblockMerge is several docblocks as one canonical block at indent: each block's content in order, a blank line
// between blocks.
func DocblockMerge(texts []string, indent string) string {
	var lines []string
	for _, text := range texts {
		content := docblockContent(text)
		if len(content) == 0 {
			continue
		}
		if len(lines) > 0 {
			lines = append(lines, "")
		}
		lines = append(lines, content...)
	}

	return docblockOf(lines, indent)
}

// DocblocksFoldable says whether blocks fold into one coherent block: no tag, with its subject, declared by two.
func DocblocksFoldable(texts []string) bool {
	seen := map[string]bool{}
	for _, text := range texts {
		tags := docTags(text)
		for index, tag := range tags {
			if slices.Contains(tags[:index], tag) {
				continue
			}
			if seen[tag] {
				return false
			}
			seen[tag] = true
		}
	}

	return true
}

// DocumentedParams is the parameter names a docblock's @param tags speak about, each without its `$`.
func DocumentedParams(text string) []string {
	var names []string
	for _, tag := range docTags(text) {
		if name, documents := strings.CutPrefix(tag, "@param $"); documents {
			names = append(names, name)
		}
	}

	return names
}

// docTags is every tag line's tag, with the variable it names when it names one: `@param $x`, `@return`.
func docTags(text string) []string {
	var tags []string
	for _, line := range docblockContent(text) {
		var words []string
		for _, word := range strings.Split(strings.ReplaceAll(line, "\t", " "), " ") {
			if word != "" {
				words = append(words, word)
			}
		}
		if len(words) == 0 || !strings.HasPrefix(words[0], "@") {
			continue
		}
		tag := words[0]
		for _, word := range words[1:] {
			if strings.HasPrefix(word, "$") {
				tag += " " + word

				break
			}
		}
		tags = append(tags, tag)
	}

	return tags
}

// docblockOf is content lines as a docblock at indent, a blank line a bare `*`.
func docblockOf(lines []string, indent string) string {
	var body strings.Builder
	for _, line := range lines {
		if line == "" {
			body.WriteString("\n" + indent + " *")
		} else {
			body.WriteString("\n" + indent + " * " + line)
		}
	}

	return "/**" + body.String() + "\n" + indent + " */"
}

// DocblockStackIsFoldable says whether the node's stacked docblocks fold into one: no blank line between two, none
// documenting a parameter the node does not take, and no tag declared twice.
func (n Node) DocblockStackIsFoldable() bool {
	blocks := n.Docblocks()
	texts := make([]string, 0, len(blocks))
	for index, block := range blocks {
		if index > 0 && block.Span.Line-docEndLine(blocks[index-1]) > 1 {
			return false
		}
		texts = append(texts, block.Text)
	}
	taken := n.ParamNames()
	for _, text := range texts {
		for _, name := range DocumentedParams(text) {
			if !slices.Contains(taken, name) {
				return false
			}
		}
	}

	return DocblocksFoldable(texts)
}

// ParamNames is the names of a function declaration's parameters, without their `$`.
func (n Node) ParamNames() []string {
	if !n.IsFunctionDeclaration() {
		return nil
	}
	var names []string
	for _, param := range Params(n.Match) {
		if name := variableName(param.Child("var")); name != "" {
			names = append(names, name)
		}
	}

	return names
}

// docEndLine is the line a comment ends on.
func docEndLine(comment contract.Comment) int {
	return comment.Span.Line + strings.Count(comment.Text, "\n")
}

// DocblockRetype is a docblock with the type it gives name changed from one class to another: in `@param T $name`,
// `@var T $name`, and a property's bare `@var T`, a generic's arguments kept.
func DocblockRetype(text, name, from, to string) string {
	quoted := regexp.QuoteMeta(name)
	rehead := func(match []string) string {
		if reheaded, ok := docReheaded(strings.TrimRight(match[2], " \t\n\r\x00\x0b"), from, to); ok {
			return match[1] + reheaded + match[3]
		}

		return match[0]
	}
	text = replaceSubmatches(regexp.MustCompile(`(@param\s+)(.+?)(\s+(?:\.\.\.)?&?\$`+quoted+`\b)`), text, rehead)
	text = replaceSubmatches(regexp.MustCompile(`(@var\s+)(.+?)(\s+\$`+quoted+`\b)`), text, rehead)

	return replaceSubmatches(regexp.MustCompile(`(?m)(@var\s+)([^\r\n]+?)(\s*)$`), text, rehead)
}

// DocblockMentionsType says whether a docblock spells a class's short name as a whole word, not part of another
// name or namespace.
func DocblockMentionsType(text, fqcn string) bool {
	short := fqcn[strings.LastIndex(fqcn, `\`)+1:]
	for at := 0; ; {
		found := strings.Index(text[at:], short)
		if found < 0 {
			return false
		}
		start, end := at+found, at+found+len(short)
		before := start > 0 && (isWordByte(text[start-1]) || text[start-1] == '\\')
		joined := end < len(text) && isWordByte(text[end]) == isWordByte(short[len(short)-1])
		if !before && !joined {
			return true
		}
		at = start + 1
	}
}

// docReheaded is a type with its head class changed, `?` and generic arguments kept; nothing when another class heads it.
func docReheaded(written, from, to string) (string, bool) {
	prefix := ""
	if strings.HasPrefix(written, "?") {
		prefix = "?"
	}
	head := written[len(prefix):]
	for _, spelling := range []string{from, `\` + from, from[strings.LastIndex(from, `\`)+1:]} {
		if head == spelling {
			return prefix + to, true
		}
		if strings.HasPrefix(head, spelling+"<") {
			return prefix + to + head[len(spelling):], true
		}
	}

	return "", false
}

// replaceSubmatches replaces every match of the pattern, left to right, with what replace makes of its groups.
func replaceSubmatches(pattern *regexp.Regexp, text string, replace func([]string) string) string {
	var out strings.Builder
	last := 0
	for _, at := range pattern.FindAllStringSubmatchIndex(text, -1) {
		groups := make([]string, len(at)/2)
		for group := range groups {
			if at[2*group] >= 0 {
				groups[group] = text[at[2*group]:at[2*group+1]]
			}
		}
		out.WriteString(text[last:at[0]])
		out.WriteString(replace(groups))
		last = at[1]
	}
	out.WriteString(text[last:])

	return out.String()
}

func isWordByte(c byte) bool {
	return c == '_' || c >= '0' && c <= '9' || c >= 'a' && c <= 'z' || c >= 'A' && c <= 'Z'
}
