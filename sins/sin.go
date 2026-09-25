// Package sins holds the sins: each is its own type naming what is wrong and the skill that fixes it.
package sins

import (
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
	// Scaffolds are the helpers the fix reaches for, which scaffold generates into the project.
	Scaffolds []Scaffold
	// Requires is the package the sin only exists in a project with; empty when it holds everywhere.
	Requires Package
}

// Scaffold is one helper file a fix needs: where it goes and the stub it is written from.
type Scaffold struct {
	Path   string
	Stub   string
	Target ScaffoldTarget
}

// ScaffoldTarget is the source root a scaffold is written into.
type ScaffoldTarget string

// The source roots a scaffold can be written into.
const (
	BackendRoot  ScaffoldTarget = "backend"
	FrontendRoot ScaffoldTarget = "frontend"
)

// Package is a dependency a project may declare.
type Package struct {
	Name      string
	Ecosystem Ecosystem
}

// Ecosystem is where a package comes from.
type Ecosystem string

// The ecosystems a package can come from.
const (
	Composer Ecosystem = "composer"
	Npm      Ecosystem = "npm"
	Pip      Ecosystem = "pip"
	NuGet    Ecosystem = "nuget"
)

var sins catalog.Catalog[Sin]

// Register enrols a sin under its engine, from the sin's own file.
func Register(engine catalog.Engine, sin Sin) {
	sins.Register(engine, sin)
}

// All is every published sin.
func All() []Sin {
	return sins.All()
}

// Every is every sin registered under one engine, unpublished ones included.
func Every(engine catalog.Engine) []Sin {
	return sins.Every(engine)
}

// Of is every published sin of one engine.
func Of(engine catalog.Engine) []Sin {
	return sins.Of(engine)
}

// Slug is the slug of the skill that fixes the sin.
func (d Definition) Slug() string {
	return d.Skill.Definition().Slug
}

// Matches says whether a query names the sin, leniently.
func (d Definition) Matches(query string) bool {
	return strings.Contains(catalog.Normalise(d.Name), catalog.Normalise(query))
}

// Scopes says whether a query names the sin or the skill that fixes it, leniently.
func (d Definition) Scopes(query string) bool {
	needle := catalog.Normalise(query)

	return strings.Contains(catalog.Normalise(d.Name), needle) || strings.Contains(catalog.Normalise(d.Slug()), needle)
}
