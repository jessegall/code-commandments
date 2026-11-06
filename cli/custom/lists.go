package custom

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strings"
)

const ListsFile = "lists.json"

// Lists are the project's named lists, by name.
type Lists map[string][]string

var named = regexp.MustCompile(`\$\{([A-Za-z][A-Za-z0-9_-]*)\}`)

func readLists(dir string, files []string) (Lists, error) {
	lists := Lists{}
	declaring := map[string]string{}

	for _, path := range files {
		read, err := readListsFile(path, shortOf(dir, path))
		if err != nil {
			return nil, err
		}

		for name, words := range read {
			if where, declared := declaring[name]; declared {
				return nil, fmt.Errorf("%s: the list %q is declared in %s too, and a rule names a list, never the file it is in", shortOf(dir, path), name, where)
			}

			declaring[name] = shortOf(dir, path)
			lists[name] = words
		}
	}

	return lists, nil
}

func readListsFile(path, short string) (Lists, error) {
	text, err := os.ReadFile(path)
	if os.IsNotExist(err) {
		return Lists{}, nil
	}
	if err != nil {
		return nil, fmt.Errorf("%s: %w", short, err)
	}

	var lists Lists
	if err := json.Unmarshal(text, &lists); err != nil {
		return nil, fmt.Errorf("%s: %w, where a list is written as {\"poetry\": [\"ephemeral\", \"sublime\"]}", short, err)
	}

	for name, words := range lists {
		if len(words) == 0 {
			return nil, fmt.Errorf("%s: the list %q is empty, so every rule naming it would check against nothing", short, name)
		}
	}

	return lists, nil
}

func shortOf(dir, path string) string {
	if short, err := filepath.Rel(dir, path); err == nil {
		return filepath.ToSlash(short)
	}

	return filepath.Base(path)
}

func (l Lists) Expand(name string, text []byte) ([]byte, error) {
	var read any
	if err := json.Unmarshal(text, &read); err != nil {
		return text, nil
	}

	expanded, err := l.expand(name, read)
	if err != nil {
		return nil, err
	}

	return json.Marshal(expanded)
}

func (l Lists) expand(rule string, read any) (any, error) {
	switch held := read.(type) {
	case string:
		return l.inString(rule, held)
	case []any:
		var out []any
		for _, each := range held {
			if words, spliced, err := l.spliced(rule, each); err != nil {
				return nil, err
			} else if spliced {
				out = append(out, words...)

				continue
			}

			expanded, err := l.expand(rule, each)
			if err != nil {
				return nil, err
			}
			out = append(out, expanded)
		}

		return out, nil
	case map[string]any:
		out := map[string]any{}
		for key, each := range held {
			expanded, err := l.expand(rule, each)
			if err != nil {
				return nil, err
			}
			out[key] = expanded
		}

		return out, nil
	}

	return read, nil
}

func (l Lists) spliced(rule string, element any) ([]any, bool, error) {
	text, is := element.(string)
	if !is || !strings.HasPrefix(text, "$") || strings.HasPrefix(text, "${") {
		return nil, false, nil
	}

	words, declared := l[strings.TrimPrefix(text, "$")]
	if !declared {
		return nil, false, l.undeclared(rule, strings.TrimPrefix(text, "$"))
	}

	var out []any
	for _, word := range words {
		out = append(out, word)
	}

	return out, true, nil
}

func (l Lists) inString(rule, text string) (string, error) {
	var failed error
	expanded := named.ReplaceAllStringFunc(text, func(reference string) string {
		name := named.FindStringSubmatch(reference)[1]
		words, declared := l[name]
		if !declared {
			failed = l.undeclared(rule, name)

			return reference
		}

		var quoted []string
		for _, word := range words {
			quoted = append(quoted, regexp.QuoteMeta(word))
		}

		return strings.Join(quoted, "|")
	})

	return expanded, failed
}

func (l Lists) undeclared(rule, name string) error {
	var declared []string
	for each := range l {
		declared = append(declared, each)
	}
	sort.Strings(declared)

	if len(declared) == 0 {
		return fmt.Errorf("%s: names the list %q, and %s declares none", rule, name, ListsFile)
	}

	return fmt.Errorf("%s: names the list %q, which %s does not declare; it has %s", rule, name, ListsFile, strings.Join(declared, ", "))
}
