package sync

import (
	"encoding/json"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"testing"
)

// The readers the plugin ships are data the journal reads: a `<name>.pattern` file whose first line says what it
// picks, whose second picks it, and whose `key: value` lines follow. Nothing held them to anything, and a reader
// that is wrong is not wrong loudly: it simply shows the wrong thing, or nothing, while a run goes by. These are
// the faults that can be read off the files themselves.

// keys are the ones the journal reads from a reader file. A key it does not know is ignored, so a typo there is a
// line that never does anything.
var keys = []string{"progress", "label", "commands", "topic", "step", "finished", "failed", "summary"}

// readerFiles are the reader files the plugin ships, by name.
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

// linesOf are a reader file's lines, less the comment it opens with and any blank one.
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

// TestEveryShippedReaderIsReadableAsTheJournalReadsIt holds each file to the shape the journal expects: it says
// what it picks, it picks something, and every key it writes is one the journal reads.
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

// TestAProgressBarIsACountOfATotal holds the fault that shipped twice before it was seen: a progress pattern with
// ONE group is read as a percentage, so a reader given a running count drew 100% as soon as the count passed a
// hundred, and the card said a run was finished while it worked. A bar is a count of a total, which is two groups,
// or it is a percentage the command really prints.
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

// TestEveryReaderAnAliasNamesIsShipped holds the two halves to each other: an alias naming a reader that is not
// there is watched by nothing, and a reader nothing names is only ever reached by hand.
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

// contains says whether the list holds the value.
func contains(list []string, value string) bool {
	for _, each := range list {
		if each == value {
			return true
		}
	}

	return false
}
