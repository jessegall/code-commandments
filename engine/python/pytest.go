package python

import (
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"sync"
)

// pytestDefault is the python_files pytest collects when a project names none.
var pytestDefault = []string{"test_*.py", "*_test.py"}

// pytestSettings are the files pytest reads its settings from, each with the section that holds them, in the order
// pytest looks for them in one folder.
var pytestSettings = []struct{ file, section string }{
	{"pytest.ini", "pytest"},
	{"pyproject.toml", "tool.pytest.ini_options"},
	{"tox.ini", "pytest"},
	{"setup.cfg", "tool:pytest"},
}

// pythonFiles reads a python_files setting's value: the quoted names of a TOML list, else the words of an INI value.
var (
	pythonFilesKey = regexp.MustCompile(`(?m)^\s*python_files\s*=\s*(.*)$`)
	quoted         = regexp.MustCompile(`["']([^"']+)["']`)
	sectionHeader  = regexp.MustCompile(`(?m)^\s*\[([^\]]+)\]\s*$`)
	pytestFiles    sync.Map
)

// pytestFilesFor is the python_files patterns pytest collects in the project the file belongs to: those the nearest
// settings file above it names, else pytest's own.
func pytestFilesFor(path string) []string {
	if path == "" {
		return pytestDefault
	}
	folder := filepath.Dir(path)
	if known, ok := pytestFiles.Load(folder); ok {
		return known.([]string)
	}
	patterns := pytestDefault
	for current := folder; ; current = filepath.Dir(current) {
		if named, found := pytestFilesIn(current); found {
			patterns = named
			break
		}
		if filepath.Dir(current) == current {
			break
		}
	}
	pytestFiles.Store(folder, patterns)

	return patterns
}

// pytestFilesIn is the python_files a settings file in the folder names, and whether one holds pytest's settings
// at all: the first that does is the project's, as pytest takes it.
func pytestFilesIn(folder string) ([]string, bool) {
	for _, settings := range pytestSettings {
		text, err := os.ReadFile(filepath.Join(folder, settings.file))
		if err != nil {
			continue
		}
		body, held := sectionOf(string(text), settings.section)
		if !held {
			continue
		}
		value := pythonFilesKey.FindStringSubmatch(body)
		if value == nil {
			return pytestDefault, true
		}

		return patternsOf(value[1]), true
	}

	return nil, false
}

// sectionOf is the text of the section the header names, up to the next header, and whether the text holds it.
func sectionOf(text, name string) (string, bool) {
	headers := sectionHeader.FindAllStringSubmatchIndex(text, -1)
	for i, header := range headers {
		if strings.TrimSpace(text[header[2]:header[3]]) != name {
			continue
		}
		end := len(text)
		if i+1 < len(headers) {
			end = headers[i+1][0]
		}

		return text[header[1]:end], true
	}

	return "", false
}

// patternsOf is the patterns a python_files value lists: quoted, as TOML writes a list, else separated by spaces.
func patternsOf(value string) []string {
	if names := quoted.FindAllStringSubmatch(value, -1); len(names) > 0 {
		patterns := make([]string, len(names))
		for i, name := range names {
			patterns[i] = name[1]
		}

		return patterns
	}

	return strings.Fields(value)
}
