// Package fixture is the self-checking fixture harness: the markers written into a fixture are the spec, and a detector passes only when it flags exactly what they mark.
package fixture

import (
	"regexp"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Tag is what a marker says of the code it marks.
type Tag string

const (
	Sinful    Tag = "sin"
	Fixed     Tag = "fixed"
	Righteous Tag = "righteous"
)

var attributeTags = map[string]Tag{"Sinful": Sinful, "Fixed": Fixed, "Righteous": Righteous}

var commentMarker = regexp.MustCompile(`^@(sin|fixed|righteous)(-file)?\s+(\w+)$`)

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

func (m Marker) Covers(finding engine.Match) bool {
	return m.covers(finding)
}

func (m Marker) Names(names ...string) bool {
	for _, name := range names {
		if m.Name == name {
			return true
		}
	}

	return false
}

func Markers(codebase *engine.Codebase) []Marker {
	return append(attributeMarkers(codebase), commentMarkers(codebase)...)
}

func attributeMarkers(codebase *engine.Codebase) []Marker {
	var markers []Marker
	for _, attribute := range codebase.WhereKind("annotation").Get() {
		tag, ok := attributeTags[ShortName(attribute.Name())]
		if !ok {
			continue
		}
		named := markedName(attribute)
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

// markedName is the sin an attribute marks, its first argument: a class constant `Sin::class` or a string.
func markedName(attribute engine.Match) string {
	arguments := attribute.ChildrenIn("arguments")
	if len(arguments) == 0 {
		return ""
	}
	argument := arguments[0].Child("value")
	if argument.Kind() == "access" && argument.Child("member").Name() == "class" {
		return ShortName(argument.Child("subject").Name())
	}
	if text, ok := argument.Text(); ok {
		return ShortName(text)
	}

	return ""
}

func commentMarkers(codebase *engine.Codebase) []Marker {
	var markers []Marker
	for _, file := range codebase.Files() {
		for _, comment := range file.File.Comments {
			for _, line := range strings.Split(comment.Text, "\n") {
				words := strings.TrimSpace(commentDelimiters.ReplaceAllString(strings.TrimSpace(line), ""))
				found := commentMarker.FindStringSubmatch(words)
				if found == nil {
					continue
				}
				marked, ok := markedFor(file, comment, found[2])
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

func markedFor(file *engine.File, comment contract.Comment, half string) (engine.Match, bool) {
	if half != "" {
		return file.Match(0), true
	}

	return markedBy(file, comment)
}

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

func classOf(node engine.Match) string {
	if node.Is(engine.TypeDeclaration) {
		return node.Identity()
	}
	if declaration := node.EnclosingType(); declaration.Exists() {
		return declaration.Identity()
	}

	return "(file)"
}

func functionOf(node engine.Match) string {
	if node.Is(engine.Function) && node.Name() != "" {
		return node.Name()
	}

	return node.EnclosingFunction().Name()
}

func ShortName(qualified string) string {
	cut := strings.LastIndexAny(qualified, `\.:`)

	return qualified[cut+1:]
}
