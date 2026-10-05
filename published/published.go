// Package published carries the facts one engine publishes for another's detectors: a server type the frontend
// must not copy by hand, the folder a generator writes its types to. A provider reads them off its own part of
// the codebase and a detector asks for them, so neither engine names the other.
package published

import (
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// Contract is one published fact.
type Contract interface {
	published()
}

// Provider publishes the contracts one engine reads off the codebase.
type Provider interface {
	Contracts(codebase *engine.Codebase) []Contract
}

var providers []Provider

// Register enrols a provider, from its own package.
func Register(provider Provider) {
	providers = append(providers, provider)
}

// Of is every contract of one type the registered providers publish for the codebase.
func Of[C Contract](codebase *engine.Codebase) []C {
	var found []C
	for _, provider := range providers {
		for _, contract := range provider.Contracts(codebase) {
			if typed, ok := contract.(C); ok {
				found = append(found, typed)
			}
		}
	}

	return found
}

// mirrorOverlap is how much of their fields a type and a server type must share to be one mirroring the other.
const mirrorOverlap = 0.8

// TypeContract is a type the server publishes: its short name and its fields, and which of those may be left out.
type TypeContract struct {
	Name     string
	Fields   []string
	Optional []string
}

func (TypeContract) published() {}

// MirroredBy says whether a hand-written type copies this one: the same name, and at least four in five of
// their fields shared, spelling aside. A field the server may leave out counts only when the copy has it.
func (c TypeContract) MirroredBy(name string, fields []string) bool {
	if canonical(name) != canonical(c.Name) {
		return false
	}
	mine, theirs := canonicalSet(c.Fields), canonicalSet(fields)
	if len(mine) == 0 || len(theirs) == 0 {
		return false
	}
	shared := 0
	union := map[string]bool{}
	for field := range mine {
		union[field] = true
		if theirs[field] {
			shared++
		}
	}
	for field := range theirs {
		union[field] = true
	}
	for field := range canonicalSet(c.Optional) {
		if !theirs[field] {
			delete(union, field)
		}
	}

	return float64(shared)/float64(len(union)) >= mirrorOverlap
}

// GeneratedTypes is the file or folder a generator writes the server's types to: its output is the one
// source of truth, never a copy.
type GeneratedTypes struct {
	Location string
}

func (GeneratedTypes) published() {}

// Covers says whether the file is the generator's output.
func (g GeneratedTypes) Covers(file string) bool {
	location := filepath.Clean(g.Location)

	return file == location || strings.HasPrefix(file, location+string(filepath.Separator))
}

// separators are the characters a name's spelling may put between its words.
var separators = strings.NewReplacer("_", "", "-", "")

// canonical folds a name's spelling: order_id, order-id and orderId read alike.
func canonical(name string) string {
	return strings.ToLower(separators.Replace(name))
}

func canonicalSet(names []string) map[string]bool {
	set := map[string]bool{}
	for _, name := range names {
		set[canonical(name)] = true
	}

	return set
}

// BlanknessQuestion is a frontend asking whether one field of a server type is blank: the server sends a blank
// where it means absent, and the page decodes it.
type BlanknessQuestion struct {
	Type  string
	Field string
}

func (BlanknessQuestion) published() {}

// AskedOf says whether the question is asked of the type's field, spelling aside.
func (q BlanknessQuestion) AskedOf(name, field string) bool {
	return canonical(name) == canonical(q.Type) && canonical(field) == canonical(q.Field)
}
