// Package state is the one format every session-scoped state file is written in, and it names itself: `name: value` lines, a divider, the list the file keeps, a divider, then the Legend saying what every value means and that deleting the file is safe.
package state

import (
	"slices"
	"strconv"
	"strings"
)

const yes = "yes"

// State is the values a file carries, in the order they were set, and the list it keeps.
type State struct {
	names  []string
	values map[string]string
	items  []string
	legend *Legend
	origin string
}

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

func Text(name, value string) Value {
	return Value{name, Flatten(value)}
}

func Int(name string, value int) Value {
	return Value{name, strconv.Itoa(value)}
}

func Flag(name string, value bool) Value {
	if value {
		return Value{name, yes}
	}

	return Value{name, "no"}
}

func Of(names []string, values map[string]string, items []string) State {
	state := State{values: map[string]string{}, items: slices.Clone(items)}

	for _, name := range names {
		state.set(name, values[name])
	}

	return state
}

func (s State) Names() []string {
	return slices.Clone(s.names)
}

func (s State) Items() []string {
	return slices.Clone(s.items)
}

func (s State) DeclaredBy(legend *Legend, origin string) State {
	s.legend, s.origin = legend, origin

	return s
}

func (s State) Has(name string) bool {
	_, set := s.values[s.declared(name)]

	return set
}

func (s State) Text(name, fallback string) string {
	if value, set := s.values[s.declared(name)]; set {
		return value
	}

	return fallback
}

func (s State) Int(name string, fallback int) int {
	if !s.Has(name) {
		return fallback
	}

	return leadingInt(s.Text(name, ""))
}

func (s State) Flag(name string) bool {
	return s.Text(name, "") == yes
}

func (s State) With(values ...Value) State {
	next := s.clone()

	for _, value := range values {
		next.set(s.declared(value.Name), value.text)
	}

	return next
}

func (s State) Merge(other State) State {
	next := s.clone()

	for _, name := range other.names {
		next.set(name, other.values[name])
	}

	next.items = slices.Clone(other.items)

	return next
}

func (s State) WithItems(items []string) State {
	next := s.clone()
	next.items = slices.Clone(items)

	return next
}

func (s State) Assignments(separator string) []string {
	var lines []string

	for _, name := range s.names {
		lines = append(lines, name+separator+s.values[name])
	}

	return lines
}

func Name(name string) string {
	return strings.ReplaceAll(name, "_", "-")
}

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

func Flatten(text string) string {
	var parts []string

	for _, part := range strings.Split(strings.ReplaceAll(text, "\r", "\n"), "\n") {
		if trimmed := strings.Trim(part, " \t\n\r\x00\x0B"); trimmed != "" {
			parts = append(parts, trimmed)
		}
	}

	return strings.Join(parts, " ")
}
