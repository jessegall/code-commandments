// Package published carries the facts one engine publishes for another's detectors: a server type the frontend must not copy by hand, the folder a generator writes its types to.
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

func Register(provider Provider) {
	providers = append(providers, provider)
}

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

const mirrorOverlap = 0.8

// TypeContract is a type the server publishes: its short name and its fields, and which of those may be left out.
type TypeContract struct {
	Name      string
	Fields    []string
	Optional  []string
	Namespace string
}

func (c TypeContract) GeneratedAs(name, namespace string) bool {
	return namespace != "" && name == c.Name && namespace == c.Namespace
}

func (TypeContract) published() {}

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

// GeneratedTypes is the file or folder a generator writes the server's types to: its output is the one source of truth, never a copy.
type GeneratedTypes struct {
	Location string
}

func (GeneratedTypes) published() {}

func (g GeneratedTypes) Covers(file string) bool {
	location := filepath.Clean(g.Location)

	return file == location || strings.HasPrefix(file, location+string(filepath.Separator))
}

var separators = strings.NewReplacer("_", "", "-", "")

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

// BlanknessQuestion is a frontend asking whether one field of a server type is blank: the server sends a blank where it means absent, and the page decodes it.
type BlanknessQuestion struct {
	Type  string
	Field string
}

func (BlanknessQuestion) published() {}

func (q BlanknessQuestion) AskedOf(name, field string) bool {
	return canonical(name) == canonical(q.Type) && canonical(field) == canonical(q.Field)
}
