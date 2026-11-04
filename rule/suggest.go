package rule

import (
	"fmt"
	"reflect"
	"slices"
	"sort"
	"strings"
)

// outer are the types a rule file is written in around its steps, whose shapes the schema spells out by hand,
// each by the name a reader of the file calls it: the object whose keys it holds, or the rule itself for the
// keys written at the top.
var outer = map[reflect.Type]string{
	reflect.TypeFor[file]():         "the rule itself",
	reflect.TypeFor[sinFile]():      `"sin"`,
	reflect.TypeFor[find]():         `"find"`,
	reflect.TypeFor[Fix]():          `"fix"`,
	reflect.TypeFor[Substitution](): `"substitute"`,
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
	for _, each := range writtenShapes() {
		for _, key := range jsonKeys(each) {
			if !slices.Contains(known, key) {
				known = append(known, key)
			}
		}
	}

	return known
}

// writtenShapes are every type a rule file is written in: the objects around the steps, and the steps.
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

// belongsIn is the hint naming where a refused key IS written, for a key that is real but misplaced: a rule
// file holding `description` at its top is not missing a key called description, it wrote `sin`'s key outside
// `sin`, and saying so is the whole correction. Empty for a key that is written nowhere, and for one a step
// holds, since a step's key is a check and the message already lists those.
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

// didYouMean is the hint naming the option nearest the word, when one is near enough to be a slip of the
// keyboard; empty when none is.
//
// An option the word spells exactly is never the hint. Every caller reaches here because the word was refused
// where it stood, so an option spelled the same is that same word offered back: a rule file with a `sin` key at
// its top read "no key is called \"description\" — did you mean \"description\"?", which tells the reader nothing
// and reads as a fault in the tool. An option that differs only in its case is still the hint, since a wrong
// case is exactly the slip a hint is for.
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
