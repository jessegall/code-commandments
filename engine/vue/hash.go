package vue

import (
	"crypto/sha1"
	"encoding/hex"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// StructureHash is the element's fingerprint: two elements share it when they are the same markup, their
// attributes in any order and their whitespace aside.
func (e Element) StructureHash() string {
	return fingerprint(e.canonical(false))
}

// ShapeHash is the element's shape: two elements share it when they differ only in what their attributes
// hold and what their text says.
func (e Element) ShapeHash() string {
	return fingerprint(e.canonical(true))
}

func (e Element) canonical(shape bool) string {
	var attributes []string
	for _, child := range e.Children() {
		if child.Kind() != "Attribute" && child.Kind() != "Directive" {
			continue
		}
		if shape {
			attributes = append(attributes, attributeName(child))
		} else {
			attributes = append(attributes, collapsed(source(child)))
		}
	}
	slices.Sort(attributes)
	var children strings.Builder
	var run []engine.Match
	flush := func() {
		if len(run) == 0 {
			return
		}
		if shape {
			children.WriteString("T")
		} else {
			children.WriteString("T:" + collapsed(textOf(run)))
		}
		run = nil
	}
	for _, child := range e.Children() {
		switch child.Kind() {
		case "Text", "Interpolation":
			run = append(run, child)
		case "Element":
			flush()
			children.WriteString(Element{child}.canonical(shape))
		}
	}
	flush()

	return "E:" + e.Tag() + "[" + strings.Join(attributes, ",") + "](" + children.String() + ")"
}

// attributeName is an attribute or directive's name as written: class, :href, @click, v-if.
func attributeName(attribute engine.Match) string {
	return attribute.Name()
}

// textOf is the source a run of text and interpolations spans.
func textOf(run []engine.Match) string {
	first, err := run[0].Span()
	if err != nil {
		return ""
	}
	last, err := run[len(run)-1].Span()
	if err != nil {
		return ""
	}
	first.End = last.End

	return first.Text()
}

func source(m engine.Match) string {
	span, err := m.Span()
	if err != nil {
		return ""
	}

	return span.Text()
}

func collapsed(text string) string {
	return strings.Join(strings.Fields(text), " ")
}

func fingerprint(canonical string) string {
	sum := sha1.Sum([]byte(canonical))

	return hex.EncodeToString(sum[:])
}
