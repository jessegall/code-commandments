package php

import (
	"regexp"
	"slices"
	"strings"

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
