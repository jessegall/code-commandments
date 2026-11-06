package sync

import (
	"encoding/json"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"testing"
)

var keys = []string{"progress", "label", "commands", "topic", "step", "finished", "failed", "summary"}

func readerFiles(t *testing.T) map[string]string {
	t.Helper()
	paths, err := filepath.Glob(filepath.Join("..", "..", ".journal-plugin", "readers", "*.pattern"))
	if err != nil {
		t.Fatal(err)
	}
	if len(paths) == 0 {
		t.Fatal("the plugin ships no reader at all")
	}

	files := map[string]string{}
	for _, path := range paths {
		content, err := os.ReadFile(path)
		if err != nil {
			t.Fatal(err)
		}
		files[strings.TrimSuffix(filepath.Base(path), ".pattern")] = string(content)
	}

	return files
}

func linesOf(file string) []string {
	var kept []string
	for _, line := range strings.Split(file, "\n") {
		if strings.TrimSpace(line) == "" || strings.HasPrefix(line, "#") {
			continue
		}
		kept = append(kept, line)
	}

	return kept
}

func TestEveryShippedReaderIsReadableAsTheJournalReadsIt(t *testing.T) {
	for name, file := range readerFiles(t) {
		t.Run(name, func(t *testing.T) {
			if !strings.HasPrefix(file, "# ") {
				t.Error("it does not open with a line saying what it picks")
			}

			lines := linesOf(file)
			if len(lines) == 0 {
				t.Fatal("it picks nothing")
			}
			if _, err := regexp.Compile(lines[0]); err != nil {
				t.Errorf("what it picks is no pattern: %v", err)
			}

			for _, line := range lines[1:] {
				key, value, named := strings.Cut(line, ": ")
				if !named {
					t.Errorf("the line %q is neither a pattern nor a key", line)

					continue
				}
				if !contains(keys, key) {
					t.Errorf("it writes %q, which the journal does not read", key)
				}
				if key != "label" && key != "commands" {
					if _, err := regexp.Compile(value); err != nil {
						t.Errorf("its %s is no pattern: %v", key, err)
					}
				}
			}
		})
	}
}

func TestAProgressBarIsACountOfATotal(t *testing.T) {
	for name, file := range readerFiles(t) {
		for _, line := range linesOf(file) {
			value, written := strings.CutPrefix(line, "progress: ")
			if !written {
				continue
			}

			pattern, err := regexp.Compile(value)
			if err != nil {
				continue
			}
			groups := pattern.NumSubexp()

			switch {
			case groups == 2:
			case groups == 1 && strings.Contains(value, "%"):
			default:
				t.Errorf("%s counts with %q, which has %d groups: a bar is a count of a total, two groups, or a percentage the command prints", name, value, groups)
			}
		}
	}
}

func TestEveryReaderAnAliasNamesIsShipped(t *testing.T) {
	content, err := os.ReadFile(filepath.Join("..", "..", "journal-plugin.json"))
	if err != nil {
		t.Fatal(err)
	}

	var manifest struct {
		Aliases []struct {
			Name   string `json:"name"`
			Reader string `json:"reader"`
		} `json:"aliases"`
	}
	if err := json.Unmarshal(content, &manifest); err != nil {
		t.Fatal(err)
	}

	shipped := readerFiles(t)
	named := map[string]bool{}

	for _, alias := range manifest.Aliases {
		if alias.Reader == "" {
			continue
		}
		named[alias.Reader] = true

		if _, there := shipped[alias.Reader]; !there {
			t.Errorf("the %s alias is watched through the %s reader, which the plugin does not ship", alias.Name, alias.Reader)
		}
	}

	for name := range shipped {
		if !named[name] {
			t.Errorf("the %s reader is named by no alias, so nothing is watched through it", name)
		}
	}
}

func contains(list []string, value string) bool {
	for _, each := range list {
		if each == value {
			return true
		}
	}

	return false
}
