// Package state is the one format every session-scoped state file is written in, and it names itself:
// `name: value` lines, a divider, the list the file keeps, a divider, then the Legend saying what every
// value means and that deleting the file is safe. Values are read and written by name, and the legend is
// the schema: a name it does not declare is refused where it is written or read.
package state

import (
	"slices"
	"strconv"
	"strings"
)

// yes is how a true flag is written.
const yes = "yes"

// State is the values a file carries, in the order they were set, and the list it keeps. A state read
// from a file knows its Legend and refuses a name the legend does not declare.
type State struct {
	names  []string
	values map[string]string
	items  []string
	legend *Legend
	origin string
}

// New is a state holding these values, none checked yet: a file checks them when it writes.
func New(values ...Value) State {
	state := State{values: map[string]string{}}

	for _, value := range values {
		state.set(Name(value.Name), value.text)
	}

	return state
}

// Value is one named value to set.
type Value struct {
	Name string
	text string
}

// Text names a text value; its lines are flattened into the one line the format stores.
func Text(name, value string) Value {
	return Value{name, Flatten(value)}
}

// Int names a number.
func Int(name string, value int) Value {
	return Value{name, strconv.Itoa(value)}
}

// Flag names a yes-or-no value.
func Flag(name string, value bool) Value {
	if value {
		return Value{name, yes}
	}

	return Value{name, "no"}
}

// Of is a state with these decoded values and list items, in order.
func Of(names []string, values map[string]string, items []string) State {
	state := State{values: map[string]string{}, items: slices.Clone(items)}

	for _, name := range names {
		state.set(name, values[name])
	}

	return state
}

// Names are the value names, in order.
func (s State) Names() []string {
	return slices.Clone(s.names)
}

// Items are the list the file keeps.
func (s State) Items() []string {
	return slices.Clone(s.items)
}

// DeclaredBy is this state bound to the legend of the file at origin, so reading an undeclared name fails.
func (s State) DeclaredBy(legend *Legend, origin string) State {
	s.legend, s.origin = legend, origin

	return s
}

// Has says whether the value is set.
func (s State) Has(name string) bool {
	_, set := s.values[s.declared(name)]

	return set
}

// Text is the value, or fallback when it is not set.
func (s State) Text(name, fallback string) string {
	if value, set := s.values[s.declared(name)]; set {
		return value
	}

	return fallback
}

// Int is the value as a number, or fallback when it is not set. A value that is no number reads as 0.
func (s State) Int(name string, fallback int) int {
	if !s.Has(name) {
		return fallback
	}

	return leadingInt(s.Text(name, ""))
}

// Flag says whether the value is yes.
func (s State) Flag(name string) bool {
	return s.Text(name, "") == yes
}

// With is this state with these values set; a bound state refuses an undeclared name.
func (s State) With(values ...Value) State {
	next := s.clone()

	for _, value := range values {
		next.set(s.declared(value.Name), value.text)
	}

	return next
}

// Merge is this state with other's values laid over it and other's list.
func (s State) Merge(other State) State {
	next := s.clone()

	for _, name := range other.names {
		next.set(name, other.values[name])
	}

	next.items = slices.Clone(other.items)

	return next
}

// WithItems is this state keeping these list items.
func (s State) WithItems(items []string) State {
	next := s.clone()
	next.items = slices.Clone(items)

	return next
}

// Assignments are the values as `name<separator>value` lines, in order.
func (s State) Assignments(separator string) []string {
	var lines []string

	for _, name := range s.names {
		lines = append(lines, name+separator+s.values[name])
	}

	return lines
}

// Name is a value's name as the file spells it: underscores become dashes.
func Name(name string) string {
	return strings.ReplaceAll(name, "_", "-")
}

// declared is the name as the file spells it. A name the bound legend does not declare is a typo in the
// code, so it panics where it is written rather than landing in a file nothing reads back.
func (s State) declared(name string) string {
	name = Name(name)

	if s.legend != nil && !s.legend.Declares(name) {
		panic(&UnknownValue{Name: name, Declared: s.legend.Names(), Path: s.origin})
	}

	return name
}

func (s *State) set(name, value string) {
	if s.values == nil {
		s.values = map[string]string{}
	}

	if _, set := s.values[name]; !set {
		s.names = append(s.names, name)
	}

	s.values[name] = value
}

func (s State) clone() State {
	next := s
	next.names = slices.Clone(s.names)
	next.values = make(map[string]string, len(s.values))

	for name, value := range s.values {
		next.values[name] = value
	}

	return next
}

// leadingInt reads a number the way PHP's (int) cast does: the leading digits, with a sign, else 0.
func leadingInt(text string) int {
	text = strings.TrimLeft(text, " \t\n\r\v\f")
	end := 0

	if end < len(text) && (text[end] == '-' || text[end] == '+') {
		end++
	}

	for end < len(text) && text[end] >= '0' && text[end] <= '9' {
		end++
	}

	number, _ := strconv.Atoi(text[:end])

	return number
}

// Flatten is text as the single line the format stores: blank lines dropped, the rest trimmed and joined
// with a space.
func Flatten(text string) string {
	var parts []string

	for _, part := range strings.Split(strings.ReplaceAll(text, "\r", "\n"), "\n") {
		if trimmed := strings.Trim(part, " \t\n\r\x00\x0B"); trimmed != "" {
			parts = append(parts, trimmed)
		}
	}

	return strings.Join(parts, " ")
}
