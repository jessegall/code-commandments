package state

import (
	"fmt"
	"slices"
	"strings"
)

// Variable is one value a file declares, with what it means.
type Variable struct {
	Name    string
	Meaning string
}

// Legend is what a state file says about itself: what it is for, every value it carries and what each
// means, the list it keeps, and that deleting it is safe. It is also the schema the file is read and
// written against.
type Legend struct {
	About     string
	Variables []Variable
	Defaults  State
	// List says what the list between the dividers holds; empty when the file keeps none.
	List string
	// Safe says why deleting the file is safe; empty is "it regenerates".
	Safe string
}

// HasList says whether the file keeps a list.
func (l *Legend) HasList() bool {
	return l.List != ""
}

// Names are the declared names, as the file spells them.
func (l *Legend) Names() []string {
	names := make([]string, len(l.Variables))

	for i, variable := range l.Variables {
		names[i] = Name(variable.Name)
	}

	return names
}

// Declares says whether the name is one of the file's values.
func (l *Legend) Declares(name string) bool {
	return slices.Contains(l.Names(), Name(name))
}

// Render is the legend as the closing section of the file.
func (l *Legend) Render() string {
	blocks := []string{l.About}

	if len(l.Variables) > 0 {
		blocks = append(blocks, l.key())
	}

	if l.HasList() {
		blocks = append(blocks, "Between the dividers is the list: "+l.List)
	}

	safe := l.Safe
	if safe == "" {
		safe = "it regenerates"
	}

	return strings.Join(append(blocks, "Safe to delete — "+safe+"."), "\n\n")
}

func (l *Legend) key() string {
	names := l.Names()
	width := 0

	for _, name := range names {
		width = max(width, len(name))
	}

	lines := []string{"The block above the first divider is this file's state, one `name: value` per line:"}

	for i, variable := range l.Variables {
		lines = append(lines, fmt.Sprintf("  %-*s  %s", width, names[i], variable.Meaning))
	}

	return strings.Join(lines, "\n")
}

// UnknownValue is a name no legend declares, read or written anyway.
type UnknownValue struct {
	Name     string
	Declared []string
	Path     string
}

func (e *UnknownValue) Error() string {
	return fmt.Sprintf("No state value named '%s' in %s — declare it in the file's Legend (with what it means) "+
		"before reading or writing it. Declared: %s.", e.Name, e.Path, strings.Join(e.Declared, ", "))
}
