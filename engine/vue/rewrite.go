package vue

import (
	"strings"
)

// WrittenAttribute is an attribute as its tag writes it, directives included: its name as written (`v-if`, `:key`, `class`), its value between its quotes when it has one, and where it stands.
type WrittenAttribute struct {
	Name   string
	Value  string
	Valued bool
	Start  int
	End    int
}

func (e Element) WrittenAttributes() []WrittenAttribute {
	span, err := e.Span()
	if err != nil {
		return nil
	}
	var written []WrittenAttribute
	for _, child := range e.Children() {
		if kind := child.Kind(); kind != "Attribute" && kind != "Directive" {
			continue
		}
		at := child.Node().Span
		written = append(written, readAttribute(string(span.Source[at.Start:at.End]), at.Start, at.End))
	}

	return written
}

func (e Element) WrittenAttribute(name string) (WrittenAttribute, bool) {
	written := e.WrittenAttributes()
	for index := len(written) - 1; index >= 0; index-- {
		if written[index].Name == name {
			return written[index], true
		}
	}

	return WrittenAttribute{}, false
}

func readAttribute(text string, start, end int) WrittenAttribute {
	at := 0
	for at < len(text) && !isSpace(text[at]) && text[at] != '=' && text[at] != '/' {
		at++
	}
	attribute := WrittenAttribute{Name: text[:at], Start: start, End: end}
	for at < len(text) && isSpace(text[at]) {
		at++
	}
	if at >= len(text) || text[at] != '=' {
		return attribute
	}
	at++
	for at < len(text) && isSpace(text[at]) {
		at++
	}
	attribute.Valued = true
	if at < len(text) && (text[at] == '"' || text[at] == '\'') {
		quote := text[at]
		closing := strings.IndexByte(text[at+1:], quote)
		if closing < 0 {
			attribute.Value = text[at+1:]
		} else {
			attribute.Value = text[at+1 : at+1+closing]
		}

		return attribute
	}
	valueEnd := at
	for valueEnd < len(text) && !isSpace(text[valueEnd]) && text[valueEnd] != '>' {
		valueEnd++
	}
	attribute.Value = text[at:valueEnd]

	return attribute
}

func isSpace(c byte) bool {
	return c == ' ' || c == '\t' || c == '\n' || c == '\v' || c == '\f' || c == '\r'
}
