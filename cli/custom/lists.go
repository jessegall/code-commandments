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

// ListsFile is the one file in the custom folder that is not a rule: the project's named lists of words, so a list
// several rules check against is written once and each of them names it.
const ListsFile = "lists.json"

// Lists are the project's named lists, by name.
type Lists map[string][]string

// named finds a list by its name.
var named = regexp.MustCompile(`\$\{([A-Za-z][A-Za-z0-9_-]*)\}`)

// readLists reads the custom folder's lists; none when there is no file, and an error the folder reports when the
// file is there and cannot be read, since a rule naming a list from it would otherwise fail for no stated reason.
func readLists(dir string) (Lists, error) {
	text, err := os.ReadFile(filepath.Join(dir, ListsFile))
	if os.IsNotExist(err) {
		return Lists{}, nil
	}
	if err != nil {
		return nil, fmt.Errorf("%s: %w", ListsFile, err)
	}

	var lists Lists
	if err := json.Unmarshal(text, &lists); err != nil {
		return nil, fmt.Errorf("%s: %w, where a list is written as {\"poetry\": [\"ephemeral\", \"sublime\"]}", ListsFile, err)
	}

	for name, words := range lists {
		if len(words) == 0 {
			return nil, fmt.Errorf("%s: the list %q is empty, so every rule naming it would check against nothing", ListsFile, name)
		}
	}

	return lists, nil
}

// Expand is the rule's text with every list it names written out: `"$poetry"` standing alone in a list of words
// becomes those words, and `${poetry}` inside any string becomes them joined for a regular expression, each quoted
// so a word with a dot in it matches that dot. A rule naming a list the project has not declared is refused, with
// the name it asked for, rather than running against nothing.
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

// expand walks the rule as read, since a list spliced into an array of words is a change to the array itself and
// cannot be made by replacing text inside a string.
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

// spliced says whether the element is a whole list named on its own, `"$poetry"`, and the words it stands for.
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

// inString writes every `${list}` inside the string out as an alternation a regular expression reads.
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

// undeclared is what a rule naming a list the project has not written is refused with.
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
