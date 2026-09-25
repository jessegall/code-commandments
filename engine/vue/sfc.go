package vue

import (
	"strings"
)

// A .vue file read as the PHP tool's own template tokenizer reads it: the blocks it holds and its template as a
// tree of tags and text. A component extraction lifts, names and rewrites markup by this tree, so what it writes
// comes out as the PHP tool writes it.

// voidTags are the tags that never hold content.
var voidTags = []string{"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "param", "source", "track", "wbr"}

// Block is one top-level block of a .vue file: its tag, its raw attribute text, its content and where the content
// starts.
type Block struct {
	Tag        string
	Attributes string
	Content    string
	Start      int
}

// HasAttribute says whether the block's opening tag writes the attribute.
func (b Block) HasAttribute(name string) bool {
	_, written := scanAttributes(b.Attributes).get(name)

	return written
}

// Sfc is a .vue file: its path, its source, its blocks and its template's tree.
type Sfc struct {
	Path     string
	Source   string
	Blocks   []Block
	Template *Markup
}

// ParseSfc reads a .vue file's source.
func ParseSfc(source, path string) *Sfc {
	sfc := &Sfc{Path: path, Source: source}
	for at := 0; at < len(source); {
		lt := strings.IndexByte(source[at:], '<')
		if lt < 0 {
			break
		}
		lt += at
		if strings.HasPrefix(source[lt:], "<!--") {
			end := strings.Index(source[lt:], "-->")
			if end < 0 {
				at = len(source)
			} else {
				at = lt + end + 3
			}

			continue
		}
		tag, attributes, selfClosing, openEnd, opens := blockOpening(source, lt)
		if !opens {
			at = lt + 1

			continue
		}
		if selfClosing {
			sfc.Blocks = append(sfc.Blocks, Block{Tag: tag, Attributes: attributes, Start: openEnd})
			at = openEnd

			continue
		}
		var content string
		if tag == "template" {
			content, at = readTemplate(source, openEnd)
		} else {
			content, at = readRaw(source, openEnd, tag)
		}
		sfc.Blocks = append(sfc.Blocks, Block{Tag: tag, Attributes: attributes, Content: content, Start: openEnd})
		if tag == "template" && sfc.Template == nil {
			sfc.Template = tokenize(content, openEnd)
		}
	}
	if sfc.Template == nil {
		sfc.Template = &Markup{Tag: "#root"}
	}

	return sfc
}

// blockOpening reads `<script …>`, `<template …>` or `<style …>` at lt: its tag, its attribute text, whether it closes
// itself, and where the opening tag ends.
func blockOpening(source string, lt int) (string, string, bool, int, bool) {
	rest := source[lt+1:]
	var tag string
	for _, candidate := range []string{"script", "template", "style"} {
		if len(rest) >= len(candidate) && strings.EqualFold(rest[:len(candidate)], candidate) {
			tag = candidate

			break
		}
	}
	if tag == "" || len(rest) > len(tag) && isWordByte(rest[len(tag)]) {
		return "", "", false, 0, false
	}
	gt := strings.IndexByte(rest[len(tag):], '>')
	if gt < 0 {
		return "", "", false, 0, false
	}
	attributes := rest[len(tag) : len(tag)+gt]
	selfClosing := strings.HasSuffix(attributes, "/")
	if selfClosing {
		attributes = attributes[:len(attributes)-1]
	}

	return tag, attributes, selfClosing, lt + 1 + len(tag) + gt + 1, true
}

func isWordByte(c byte) bool {
	return c == '_' || c >= '0' && c <= '9' || c >= 'a' && c <= 'z' || c >= 'A' && c <= 'Z'
}

func readRaw(source string, from int, tag string) (string, int) {
	closing := strings.Index(strings.ToLower(source[from:]), "</"+tag)
	if closing < 0 {
		return source[from:], len(source)
	}
	closing += from
	gt := strings.IndexByte(source[closing:], '>')
	if gt < 0 {
		return source[from:closing], len(source)
	}

	return source[from:closing], closing + gt + 1
}

func readTemplate(source string, from int) (string, int) {
	depth := 1
	for at := from; at < len(source); {
		lt := strings.IndexByte(source[at:], '<')
		if lt < 0 {
			break
		}
		lt += at
		closing := strings.HasPrefix(source[lt+1:], "/")
		name := source[lt+1:]
		if closing {
			name = name[1:]
		}
		if len(name) >= len("template") && strings.EqualFold(name[:len("template")], "template") && (len(name) == len("template") || !isWordByte(name[len("template")])) {
			if closing {
				depth--
			} else {
				depth++
			}
			if depth == 0 {
				gt := strings.IndexByte(source[lt:], '>')
				if gt < 0 {
					return source[from:lt], len(source)
				}

				return source[from:lt], lt + gt + 1
			}
		}
		at = lt + 1
	}

	return source[from:], len(source)
}

// ScriptContent is every script block's content, joined by line breaks.
func (s *Sfc) ScriptContent() string {
	var parts []string
	for _, block := range s.Blocks {
		if block.Tag == "script" {
			parts = append(parts, block.Content)
		}
	}

	return strings.Join(parts, "\n")
}

// ScriptContentStart is where the `<script setup>` content starts, else the first script's.
func (s *Sfc) ScriptContentStart() (int, bool) {
	start, found := 0, false
	for _, block := range s.Blocks {
		if block.Tag != "script" {
			continue
		}
		if block.HasAttribute("setup") {
			return block.Start, true
		}
		if !found {
			start, found = block.Start, true
		}
	}

	return start, found
}

// Markup is a node of a template as the PHP tool's tokenizer reads it: `#root`, `#text`, `#comment`, or a tag.
type Markup struct {
	Tag        string
	Attributes attributeList
	Children   []*Markup
	Parent     *Markup
	Text       string
	Start      int
	End        int
}

// attribute is one attribute a tag writes: its name, its value if it has one, and where it stands.
type attribute struct {
	Name   string
	Value  string
	Valued bool
	Start  int
	End    int
}

// attributeList is a tag's attributes in the order it first writes each name, a name written again keeping its place
// and taking its last value and span.
type attributeList []attribute

func (l attributeList) get(name string) (attribute, bool) {
	for _, written := range l {
		if written.Name == name {
			return written, true
		}
	}

	return attribute{}, false
}

func (l *attributeList) put(written attribute) {
	for index := range *l {
		if (*l)[index].Name == written.Name {
			(*l)[index] = written

			return
		}
	}
	*l = append(*l, written)
}

// scanAttributes reads a tag's attribute text as the PHP tool's attribute scanner does.
func scanAttributes(raw string) attributeList {
	var attributes attributeList
	for at := 0; at < len(raw); {
		for at < len(raw) && isSpace(raw[at]) {
			at++
		}
		if at >= len(raw) {
			break
		}
		start := at
		for at < len(raw) && !isSpace(raw[at]) && raw[at] != '=' && raw[at] != '/' {
			at++
		}
		name := raw[start:at]
		if name == "" {
			at++

			continue
		}
		afterName := at
		for at < len(raw) && isSpace(raw[at]) {
			at++
		}
		written := attribute{Name: name, Start: start}
		if at < len(raw) && raw[at] == '=' {
			at++
			written.Value, at = readAttributeValue(raw, at)
			written.Valued, written.End = true, at
		} else {
			written.End, at = afterName, afterName
		}
		attributes.put(written)
	}

	return attributes
}

func readAttributeValue(raw string, at int) (string, int) {
	for at < len(raw) && isSpace(raw[at]) {
		at++
	}
	if at < len(raw) && (raw[at] == '"' || raw[at] == '\'') {
		quote := raw[at]
		at++
		start := at
		for at < len(raw) && raw[at] != quote {
			at++
		}

		return raw[start:at], at + 1
	}
	start := at
	for at < len(raw) && !isSpace(raw[at]) && raw[at] != '>' {
		at++
	}

	return raw[start:at], at
}

// frame is a tag the tokenizer has opened and not yet closed.
type frame struct {
	node *Markup
}

type tokenizer struct {
	html   string
	offset int
	stack  []*Markup
}

// tokenize reads a template's content, its offsets shifted to where the content sits in its file.
func tokenize(html string, offset int) *Markup {
	t := &tokenizer{html: html, offset: offset, stack: []*Markup{{Tag: "#root", Start: offset}}}
	for at := 0; ; {
		lt := strings.IndexByte(html[at:], '<')
		textEnd := len(html)
		if lt >= 0 {
			lt += at
			textEnd = lt
		}
		t.emitText(html[at:textEnd], at)
		if lt < 0 {
			break
		}
		switch {
		case strings.HasPrefix(html[lt:], "<!--"):
			end := strings.Index(html[lt:], "-->")
			commentEnd, closing := len(html), len(html)
			if end >= 0 {
				commentEnd, closing = lt+end, lt+end+3
			}
			t.append(&Markup{Tag: "#comment", Text: strings.Trim(html[lt+4:commentEnd], " \t\n\r\x00\x0b"), Start: lt + offset, End: closing + offset})
			at = closing
		case strings.HasPrefix(html[lt:], "</"):
			at = t.closeTag(lt)
		default:
			tag := tagName(html[lt+1:])
			if tag == "" {
				t.emitText("<", lt)
				at = lt + 1

				continue
			}
			at = t.openTag(lt, tag)
		}
	}
	for len(t.stack) > 1 {
		t.fold(len(html))
	}
	root := t.stack[0]
	root.End = len(html) + offset
	for _, child := range root.Children {
		child.Parent = root
	}

	return root
}

// tagName is the name a tag opens with: a letter, then word characters, dots, dashes and colons.
func tagName(rest string) string {
	if rest == "" || !(rest[0] >= 'a' && rest[0] <= 'z' || rest[0] >= 'A' && rest[0] <= 'Z') {
		return ""
	}
	end := 1
	for end < len(rest) && (isWordByte(rest[end]) || rest[end] == '.' || rest[end] == '-' || rest[end] == ':') {
		end++
	}

	return rest[:end]
}

func (t *tokenizer) tagEnd(from int) int {
	for at := from; at < len(t.html); at++ {
		switch c := t.html[at]; c {
		case '>':
			return at
		case '"', '\'':
			at = skipQuoted(t.html, at, c) - 1
		}
	}

	return len(t.html)
}

func skipQuoted(source string, at int, quote byte) int {
	for at++; at < len(source); at++ {
		if source[at] == '\\' {
			at++
		} else if source[at] == quote {
			return at + 1
		}
	}

	return len(source)
}

func (t *tokenizer) openTag(lt int, tag string) int {
	innerStart := lt + 1 + len(tag)
	end := t.tagEnd(innerStart)
	inner := t.html[innerStart:end]
	trimmed := strings.TrimRight(inner, " \t\n\r\x00\x0b")
	selfClosing := strings.HasSuffix(trimmed, "/")
	parsed := inner
	if selfClosing {
		parsed = trimmed[:len(trimmed)-1]
	}
	attributes := scanAttributes(parsed)
	spans := scanAttributes(inner)
	for index := range attributes {
		if span, ok := spans.get(attributes[index].Name); ok {
			attributes[index].Start, attributes[index].End = span.Start+innerStart+t.offset, span.End+innerStart+t.offset
		}
	}
	node := &Markup{Tag: tag, Attributes: attributes, Start: lt + t.offset}
	if selfClosing || containsFold(voidTags, tag) {
		node.End = end + 1 + t.offset
		t.append(node)
	} else {
		t.stack = append(t.stack, node)
	}

	return end + 1
}

func containsFold(list []string, value string) bool {
	for _, item := range list {
		if item == strings.ToLower(value) {
			return true
		}
	}

	return false
}

func (t *tokenizer) closeTag(lt int) int {
	gt := strings.IndexByte(t.html[lt:], '>')
	end, nameEnd := len(t.html), len(t.html)
	if gt >= 0 {
		end, nameEnd = lt+gt+1, lt+gt
	}
	tag := strings.Trim(t.html[lt+2:max(lt+2, nameEnd)], " \t\n\r\x00\x0b")
	for depth := len(t.stack) - 1; depth >= 1; depth-- {
		if t.stack[depth].Tag != tag {
			continue
		}
		for len(t.stack)-1 >= depth {
			t.fold(end)
		}

		break
	}

	return end
}

func (t *tokenizer) fold(end int) {
	node := t.stack[len(t.stack)-1]
	t.stack = t.stack[:len(t.stack)-1]
	node.End = end + t.offset
	for _, child := range node.Children {
		child.Parent = node
	}
	t.append(node)
}

func (t *tokenizer) emitText(text string, at int) {
	if strings.Trim(text, " \t\n\r\x00\x0b") == "" {
		return
	}
	t.append(&Markup{Tag: "#text", Text: text, Start: at + t.offset, End: at + t.offset + len(text)})
}

func (t *tokenizer) append(node *Markup) {
	top := t.stack[len(t.stack)-1]
	top.Children = append(top.Children, node)
}
