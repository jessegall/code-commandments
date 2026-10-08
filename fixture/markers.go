// Package fixture is the self-checking fixture harness: the markers written into a fixture are the
// spec, and a detector passes only when it flags exactly what they mark.
package fixture

import (
	"regexp"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Tag is what a marker says of the code it marks.
type Tag string

// The tags, as attributes and comments spell them.
const (
	Sinful    Tag = "sin"
	Fixed     Tag = "fixed"
	Righteous Tag = "righteous"
)

// attributeTags are the tags an attribute spells by its class's short name.
var attributeTags = map[string]Tag{"Sinful": Sinful, "Fixed": Fixed, "Righteous": Righteous}

// commentMarker is a marker as a comment writes it, the comment's whole text: @sin ArrayBag marks the code the
// comment leads, and @sin-file ArrayBag the whole file, where a finding about the file itself stands, wherever the
// comment sits. A comment that only mentions a marker in its prose marks nothing. Scanning comment text, not code.
var commentMarker = regexp.MustCompile(`^@(sin|fixed|righteous)(-file)?\s+(\w+)$`)

// commentDelimiters are what opens and closes a comment in every language the fixtures are written in.
var commentDelimiters = regexp.MustCompile(`^(?:#|//+|/\*+|<!--)|(?:\*/|-->)$`)

// Marker is one mark in a fixture: a tag, the sin or detector it names, and the code it covers.
type Marker struct {
	Tag      Tag
	Name     string
	File     string
	Class    string
	Location string
	covers   func(engine.Match) bool
}

// Covers says whether a finding falls on the code the marker marks.
func (m Marker) Covers(finding engine.Match) bool {
	return m.covers(finding)
}

// Names says whether the marker names one of these short names.
func (m Marker) Names(names ...string) bool {
	for _, name := range names {
		if m.Name == name {
			return true
		}
	}

	return false
}

// Markers is every marker in the codebase: attributes on declarations and comments above code.
func Markers(codebase *engine.Codebase) []Marker {
	return append(attributeMarkers(codebase), commentMarkers(codebase)...)
}

// attributeMarkers reads #[Sinful(X::class)] and its kin; each covers the declaration it stands on,
// a class-level one every function in the class.
func attributeMarkers(codebase *engine.Codebase) []Marker {
	var markers []Marker
	for _, attribute := range codebase.WhereKind("Attribute").Get() {
		tag, ok := attributeTags[ShortName(attribute.Child("name").Name())]
		if !ok {
			continue
		}
		named := markedName(attribute.Child("args").Child("value"))
		if named == "" {
			continue
		}
		class := classOf(attribute)
		function := attribute.EnclosingFunction().Name()
		markers = append(markers, Marker{
			Tag:      tag,
			Name:     named,
			File:     attribute.File(),
			Class:    class,
			Location: attribute.Location(),
			covers: func(finding engine.Match) bool {
				return classOf(finding) == class && (function == "" || functionOf(finding) == function)
			},
		})
	}

	return markers
}

// markedName is the short name an attribute's first argument gives: X::class or 'X'.
func markedName(argument engine.Match) string {
	if class := argument.Child("class"); class.Exists() {
		return ShortName(class.Name())
	}
	if text, ok := argument.Text(); ok {
		return ShortName(text)
	}

	return ""
}

// commentMarkers reads @sin X from comments; a run of them marks the line of the node they lead.
func commentMarkers(codebase *engine.Codebase) []Marker {
	var markers []Marker
	for _, file := range codebase.Files() {
		for _, comment := range file.File.Comments {
			words := strings.TrimSpace(commentDelimiters.ReplaceAllString(strings.TrimSpace(comment.Text), ""))
			for _, found := range commentMarker.FindAllStringSubmatch(words, -1) {
				marked, ok := file.Match(0), true
				if found[2] == "" {
					marked, ok = markedBy(file, comment)
				}
				if !ok {
					continue
				}
				location := marked.Location()
				markers = append(markers, Marker{
					Tag:      Tag(found[1]),
					Name:     found[3],
					File:     file.Path,
					Class:    classOf(marked),
					Location: location,
					covers:   func(finding engine.Match) bool { return finding.Location() == location },
				})
			}
		}
	}

	return markers
}

// markedBy is the code a marker comment marks: the node it leads, or, for a comment on a line of its own that leads
// no node, as above `: throw …` where the next token starts none, the first node written after it.
func markedBy(file *engine.File, comment contract.Comment) (engine.Match, bool) {
	if comment.Attached != nil {
		return file.Match(*comment.Attached), true
	}
	if comment.Trailing {
		return engine.Match{}, false
	}
	var next engine.Match
	for _, node := range file.Match(0).Descendants() {
		if start := node.Node().Span.Start; start >= comment.Span.End && (!next.Exists() || start < next.Node().Span.Start) {
			next = node
		}
	}

	return next, next.Exists()
}

// classOf is the type a node belongs to, itself when it is one, (file) outside any.
func classOf(node engine.Match) string {
	if node.Is(engine.TypeDeclaration) {
		return node.Identity()
	}
	if declaration := node.EnclosingType(); declaration.Exists() {
		return declaration.Identity()
	}

	return "(file)"
}

// functionOf is the named function a node belongs to, itself when it is one.
func functionOf(node engine.Match) string {
	if node.Is(engine.Function) && node.Name() != "" {
		return node.Name()
	}

	return node.EnclosingFunction().Name()
}

// ShortName is a qualified name's last segment: Shop\Sins\ArrayBag, Shop.Sins.ArrayBag → ArrayBag.
func ShortName(qualified string) string {
	cut := strings.LastIndexAny(qualified, `\.:`)

	return qualified[cut+1:]
}
