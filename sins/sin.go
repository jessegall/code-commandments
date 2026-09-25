// Package sins holds the sins: each is its own type naming what is wrong and the skill that fixes it.
package sins

import (
	"encoding/json"
	"os"
	"path/filepath"
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
	// Requires is the package the sin only exists in a project with; empty when it holds everywhere.
	Requires Package
}

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

// InstalledIn says whether the project at root depends on the package, as composer installed it — itself, or
// replaced or provided by a package it installed, as laravel/framework replaces illuminate/support. A project composer
// never installed into has none of its packages, as the PHP tool finds none in an install that lacks them, so a project
// with no PHP never runs Laravel rules; a record composer wrote but that cannot be read is taken to have it, so a rule
// is never silenced by a broken file. Only composer is read here; the other ecosystems' manifests arrive with their
// engines.
func (p Package) InstalledIn(root string) bool {
	if p.Ecosystem != Composer {
		return true
	}
	content, err := os.ReadFile(filepath.Join(root, "vendor", "composer", "installed.json"))
	if err != nil {
		return false
	}
	var installed struct {
		Packages []struct {
			Name    string            `json:"name"`
			Replace map[string]string `json:"replace"`
			Provide map[string]string `json:"provide"`
		} `json:"packages"`
	}
	if json.Unmarshal(content, &installed) != nil {
		return true
	}
	for _, each := range installed.Packages {
		_, replaced := each.Replace[p.Name]
		_, provided := each.Provide[p.Name]
		if each.Name == p.Name || replaced || provided {
			return true
		}
	}

	return false
}
