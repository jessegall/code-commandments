package state

import (
	"os"
	"path/filepath"
	"regexp"
	"strings"

	"github.com/jessegall/code-commandments/cli/atomic"
)

const (
	divider = "-----"
	assign  = ": "
)

// lineBreak is any line ending a file may have been written with.
var lineBreak = regexp.MustCompile(`\r\n|[\n\r\x0B\f]`)

// File is one state file on disk and the legend it is read and written against.
type File struct {
	path   string
	legend *Legend
}

// At is the state file at path, declared by legend.
func At(path string, legend *Legend) File {
	return File{path, legend}
}

// Path is where the file lives.
func (f File) Path() string {
	return f.path
}

// Exists says whether the file is on disk.
func (f File) Exists() bool {
	info, err := os.Stat(f.path)

	return err == nil && info.Mode().IsRegular()
}

// Read is the decoded state, or the empty state when the file is not there, so absence is never a case a
// caller handles. A value an older shape of the file left behind, under a name no longer declared, is
// dropped.
func (f File) Read() State {
	raw, err := os.ReadFile(f.path)
	if err != nil {
		return New()
	}

	sections := f.sections(string(raw))
	var names []string
	values := map[string]string{}

	if len(sections) > 0 {
		for _, line := range sections[0] {
			name, value, found := strings.Cut(line, assign)

			if !found || name == "" || !f.legend.Declares(name) {
				continue
			}

			if _, seen := values[name]; !seen {
				names = append(names, name)
			}

			values[name] = value
		}
	}

	var items []string

	if f.legend.HasList() && len(sections) > 1 {
		items = sections[1]
	}

	return Of(names, values, items).DeclaredBy(f.legend, f.path)
}

// Write stores the state over the legend's defaults, then the list when the file keeps one, then the
// legend. A value the legend does not declare panics as an UnknownValue. The write is whole or not at all,
// since hooks write these from several processes at once.
func (f File) Write(s State) error {
	for _, name := range s.names {
		if !f.legend.Declares(name) {
			panic(&UnknownValue{Name: name, Declared: f.legend.Names(), Path: f.path})
		}
	}

	s = f.legend.Defaults.Merge(s)
	sections := [][]string{s.Assignments(assign)}

	if f.legend.HasList() {
		sections = append(sections, s.Items())
	}

	sections = append(sections, []string{f.legend.Render()})

	var lines []string

	for _, section := range sections {
		lines = append(append(lines, section...), divider)
	}

	lines = lines[:len(lines)-1]

	if err := os.MkdirAll(filepath.Dir(f.path), 0o777); err != nil {
		return err
	}

	return atomic.Write(f.path, strings.Join(lines, "\n")+"\n")
}

// Delete removes the file; a file already gone is fine.
func (f File) Delete() {
	os.Remove(f.path)
}

// sections splits the file at its dividers, each section as its non-blank lines. What follows the last
// divider is the legend, prose never read back, so it is dropped.
func (f File) sections(contents string) [][]string {
	var sections [][]string
	var current []string

	for _, line := range lineBreak.Split(contents, -1) {
		if line == divider {
			sections = append(sections, current)
			current = nil

			continue
		}

		if strings.Trim(line, " \t\n\r\x00\x0B") != "" {
			current = append(current, line)
		}
	}

	return sections
}
