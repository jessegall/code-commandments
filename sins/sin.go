// Package sins holds the sins: each is its own type naming what is wrong and the skill that fixes it.
package sins

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

// Sin is one sin, declared by its type.
type Sin interface {
	Definition() Definition
}

// Definition is what a sin states about itself.
type Definition struct {
	Name        string
	Skill       skill.Skill
	Description string
	Rule        string
	Suggestion  string
	Requires    Package
	Formerly    []string
}

// Package is a dependency a project may declare.
type Package struct {
	Name      string
	Ecosystem Ecosystem
	Version   string
}

// Ecosystem is where a package comes from.
type Ecosystem string

const (
	Composer Ecosystem = "composer"
	Npm      Ecosystem = "npm"
	Pip      Ecosystem = "pip"
	NuGet    Ecosystem = "nuget"
)

var sins catalog.Catalog[Sin]

func Register(engine catalog.Engine, sin Sin) {
	sins.Register(engine, sin)
}

func All() []Sin {
	return sins.All()
}

func EngineOf(sin Sin) (catalog.Engine, bool) {
	return sins.EngineOf(sin)
}

func (d Definition) Slug() string {
	return d.Skill.Definition().Slug
}

func (d Definition) Matches(query string) bool {
	needle := catalog.Normalise(query)

	return slices.ContainsFunc(d.Names(), func(name string) bool { return strings.Contains(catalog.Normalise(name), needle) })
}

func (d Definition) Scopes(query string) bool {
	return d.Matches(query) || strings.Contains(catalog.Normalise(d.Slug()), catalog.Normalise(query))
}

func (d Definition) Names() []string {
	return append([]string{d.Name}, d.Formerly...)
}
