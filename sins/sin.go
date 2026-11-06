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
	Requires    Package
}

// Package is a dependency a project may declare.
type Package struct {
	Name      string
	Ecosystem Ecosystem
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

func Every(engine catalog.Engine) []Sin {
	return sins.Every(engine)
}

func Of(engine catalog.Engine) []Sin {
	return sins.Of(engine)
}

func (d Definition) Slug() string {
	return d.Skill.Definition().Slug
}

func (d Definition) Matches(query string) bool {
	return strings.Contains(catalog.Normalise(d.Name), catalog.Normalise(query))
}

func (d Definition) Scopes(query string) bool {
	needle := catalog.Normalise(query)

	return strings.Contains(catalog.Normalise(d.Name), needle) || strings.Contains(catalog.Normalise(d.Slug()), needle)
}

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
