package rule

import (
	"fmt"
	"reflect"
	"slices"
	"sort"
	"strings"
)

var outer = map[reflect.Type]string{
	reflect.TypeFor[file]():         "the rule itself",
	reflect.TypeFor[sinFile]():      `"sin"`,
	reflect.TypeFor[find]():         `"find"`,
	reflect.TypeFor[Fix]():          `"fix"`,
	reflect.TypeFor[Substitution](): `"substitute"`,
}

func jsonKeys(written reflect.Type) []string {
	var found []string
	for i := range written.NumField() {
		field := written.Field(i)
		if field.Anonymous {
			found = append(found, jsonKeys(field.Type)...)

			continue
		}

		if key, _, _ := strings.Cut(field.Tag.Get("json"), ","); key != "" && key != "-" {
			found = append(found, key)
		}
	}

	return found
}

func knownKeys() []string {
	var known []string
	for _, each := range writtenShapes() {
		for _, key := range jsonKeys(each) {
			if !slices.Contains(known, key) {
				known = append(known, key)
			}
		}
	}

	return known
}

func writtenShapes() []reflect.Type {
	var written []reflect.Type
	for shape := range outer {
		written = append(written, shape)
	}

	for shape := range shapes {
		written = append(written, shape)
	}

	return written
}

func belongsIn(key string) string {
	var owners []string

	for shape, name := range outer {
		if !slices.Contains(jsonKeys(shape), key) {
			continue
		}

		if name == outer[reflect.TypeFor[file]()] {
			continue
		}

		if !slices.Contains(owners, name) {
			owners = append(owners, name)
		}
	}

	sort.Strings(owners)

	if len(owners) == 0 {
		return ""
	}

	return fmt.Sprintf(" — %q is a key of %s", key, strings.Join(owners, " and "))
}

func didYouMean(word string, options []string) string {
	best, bestDistance := "", len(word)/3+2

	for _, option := range options {
		if option == word {
			continue
		}

		if distance := editDistance(strings.ToLower(word), strings.ToLower(option)); distance < bestDistance {
			best, bestDistance = option, distance
		}
	}

	if best == "" {
		return ""
	}

	return " — did you mean " + `"` + best + `"?`
}

func editDistance(from, to string) int {
	previous := make([]int, len(to)+1)
	for j := range previous {
		previous[j] = j
	}

	for i := 1; i <= len(from); i++ {
		current := make([]int, len(to)+1)
		current[0] = i

		for j := 1; j <= len(to); j++ {
			substitution := previous[j-1]
			if from[i-1] != to[j-1] {
				substitution++
			}

			current[j] = min(previous[j]+1, current[j-1]+1, substitution)
		}

		previous = current
	}

	return previous[len(to)]
}

func unknownKey(err error) (string, bool) {
	key, unknown := strings.CutPrefix(err.Error(), "json: unknown field ")

	return strings.Trim(key, `"`), unknown
}
