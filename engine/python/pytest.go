package python

import (
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"sync"
)

var pytestDefault = []string{"test_*.py", "*_test.py"}

var pytestSettings = []struct{ file, section string }{
	{"pytest.ini", "pytest"},
	{"pyproject.toml", "tool.pytest.ini_options"},
	{"tox.ini", "pytest"},
	{"setup.cfg", "tool:pytest"},
}

var (
	pythonFilesKey = regexp.MustCompile(`(?m)^\s*python_files\s*=\s*(.*)$`)
	quoted         = regexp.MustCompile(`["']([^"']+)["']`)
	sectionHeader  = regexp.MustCompile(`(?m)^\s*\[([^\]]+)\]\s*$`)
	pytestFiles    sync.Map
)

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

var raisesAssertions = []string{"raises", "assertRaises", "assertRaisesRegex"}
