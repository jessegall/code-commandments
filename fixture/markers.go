// Package fixture is the self-checking fixture harness: the markers written into a fixture are the
// spec, and a detector passes only when it flags exactly what they mark.
package fixture

import (
	"regexp"
	"strings"

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

// commentMarker is a marker as a comment writes it: @sin ArrayBag. Scanning comment text, not code.
var commentMarker = regexp.MustCompile(`@(sin|fixed|righteous)\s+(\w+)`)

// Marker is one mark in a fixture: a tag, the sin or detector it names, and the code it covers.
type Marker struct {
	Tag      Tag
	Name     string
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
		class := attribute.EnclosingType().Identity()
		function := attribute.EnclosingFunction().Name()
		markers = append(markers, Marker{
			Tag:      tag,
			Name:     named,
			Location: attribute.Location(),
			covers: func(finding engine.Match) bool {
				return finding.EnclosingType().Identity() == class &&
					(function == "" || finding.EnclosingFunction().Name() == function)
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
			if comment.Attached == nil {
				continue
			}
			location := file.Match(*comment.Attached).Location()
			for _, found := range commentMarker.FindAllStringSubmatch(comment.Text, -1) {
				markers = append(markers, Marker{
					Tag:      Tag(found[1]),
					Name:     found[2],
					Location: location,
					covers:   func(finding engine.Match) bool { return finding.Location() == location },
				})
			}
		}
	}

	return markers
}

// ShortName is a qualified name's last segment: Shop\Sins\ArrayBag, Shop.Sins.ArrayBag → ArrayBag.
func ShortName(qualified string) string {
	cut := strings.LastIndexAny(qualified, `\.:`)

	return qualified[cut+1:]
}
