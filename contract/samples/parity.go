package samples

import (
	"fmt"
	"maps"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/contract/marker"
)

// Exclusion is one row of the samples' exclusion table: a key one language truly cannot express for a construct the
// other languages mark.
type Exclusion struct {
	Construct string
	Key       string
	Language  contract.Language
	Why       string
}

func ExclusionsFrom(file string, text []byte, constructs []string) ([]Exclusion, []error) {
	items := contract.Items()
	var exclusions []Exclusion
	var faults []error
	inTable := false
	for number, line := range strings.Split(string(text), "\n") {
		if strings.HasPrefix(line, "## ") {
			inTable = strings.TrimSpace(strings.TrimPrefix(line, "## ")) == "Exclusions"
		}
		cells := strings.Split(line, "|")
		if !inTable || len(cells) < 6 || strings.HasPrefix(strings.TrimSpace(cells[1]), "-") || strings.TrimSpace(cells[1]) == "Construct" {
			continue
		}
		row := Exclusion{
			Construct: strings.Trim(strings.TrimSpace(cells[1]), "`"),
			Key:       strings.Trim(strings.TrimSpace(cells[2]), "`"),
			Language:  contract.Language(strings.TrimSpace(cells[3])),
			Why:       strings.TrimSpace(cells[4]),
		}
		_, knownKey := slices.BinarySearch(items, row.Key)
		switch {
		case !slices.Contains(constructs, row.Construct):
			faults = append(faults, fmt.Errorf("%s:%d: %s is no sample construct", file, number+1, row.Construct))
		case !knownKey:
			faults = append(faults, fmt.Errorf("%s:%d: %s is no contract item", file, number+1, row.Key))
		case !slices.Contains(contract.Languages, row.Language):
			faults = append(faults, fmt.Errorf("%s:%d: %s is no language", file, number+1, row.Language))
		case row.Why == "":
			faults = append(faults, fmt.Errorf("%s:%d: %s for %s is excluded without a reason", file, number+1, row.Key, row.Language))
		default:
			exclusions = append(exclusions, row)
		}
	}

	return exclusions, faults
}

func Parity(construct string, sources map[contract.Language]marker.Source, exclusions []Exclusion) []error {
	if len(sources) < 2 {
		return nil
	}
	assertions := map[contract.Language][]string{}
	for language, source := range sources {
		assertions[language] = assertionsOf(source)
	}
	languages := slices.Sorted(maps.Keys(sources))
	var faults []error
	for _, writer := range languages {
		for _, assertion := range assertions[writer] {
			key, _, _ := strings.Cut(assertion, " ")
			for _, other := range languages {
				if other == writer || excluded(exclusions, construct, key, other) {
					continue
				}
				if count(assertions[other], assertion) < count(assertions[writer], assertion) {
					faults = append(faults, fmt.Errorf("%s: %s marks %q, %s does not", construct, writer, assertion, other))
				}
			}
		}
	}

	return slices.CompactFunc(faults, func(a, b error) bool { return a.Error() == b.Error() })
}

func assertionsOf(source marker.Source) []string {
	var written []string
	for _, each := range source.Markers {
		if each.Key == "" {
			continue
		}
		parts := []string{each.Key}
		if each.Owner != "" {
			parts = append(parts, "on", each.Owner)
		}
		if each.Operator != "" {
			parts = append(parts, each.Operator)
		}
		for _, target := range each.Targets {
			parts = append(parts, targetName(source, target))
		}
		if len(each.Value) > 0 {
			parts = append(parts, string(each.Value))
		}
		written = append(written, strings.Join(parts, " "))
	}
	slices.Sort(written)

	return written
}

func targetName(source marker.Source, target marker.Target) string {
	if target.Line == 0 {
		return target.Label
	}
	for _, each := range source.Markers {
		if each.Label != "" && each.Line == target.Line && each.Owner == target.Kind {
			return each.Label
		}
	}

	return "line(" + target.Kind + ")"
}

func excluded(exclusions []Exclusion, construct, key string, language contract.Language) bool {
	return slices.ContainsFunc(exclusions, func(row Exclusion) bool {
		return row.Construct == construct && row.Key == key && row.Language == language
	})
}

func count(assertions []string, assertion string) int {
	found := 0
	for _, each := range assertions {
		if each == assertion {
			found++
		}
	}

	return found
}
