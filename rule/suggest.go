package rule

import (
	"reflect"
	"slices"
	"strings"
)

// written are the types a rule file is written in, whose keys a misspelt one is compared with.
var written = []reflect.Type{
	reflect.TypeFor[file](), reflect.TypeFor[sinFile](), reflect.TypeFor[find](), reflect.TypeFor[Step](),
	reflect.TypeFor[Bounds](), reflect.TypeFor[Count](), reflect.TypeFor[Argued](), reflect.TypeFor[Nesting](),
}

// jsonKeys are the keys a type is written with, an embedded type's among them.
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

// knownKeys are every key a rule file may use.
func knownKeys() []string {
	var known []string
	for _, each := range written {
		for _, key := range jsonKeys(each) {
			if !slices.Contains(known, key) {
				known = append(known, key)
			}
		}
	}

	return known
}

// didYouMean is the hint naming the option nearest the word, when one is near enough to be a slip of the
// keyboard; empty when none is.
func didYouMean(word string, options []string) string {
	best, bestDistance := "", len(word)/3+2

	for _, option := range options {
		if distance := editDistance(strings.ToLower(word), strings.ToLower(option)); distance < bestDistance {
			best, bestDistance = option, distance
		}
	}

	if best == "" {
		return ""
	}

	return " — did you mean " + `"` + best + `"?`
}

// editDistance is how many letters must be added, removed or changed to turn one word into the other.
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

// unknownKey is the key a decoder refused as unknown, from its error; false for any other error.
func unknownKey(err error) (string, bool) {
	key, unknown := strings.CutPrefix(err.Error(), "json: unknown field ")

	return strings.Trim(key, `"`), unknown
}
