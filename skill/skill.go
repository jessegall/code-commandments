// Package skill holds the teaching layer's metadata: each Skill names a discipline, and a sin points at the skill that fixes it.
package skill

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
)

// Tier says how often a skill comes up: constantly in play, or on contact with its subject.
type Tier string

const (
	Mandatory  Tier = "mandatory"
	KeepInMind Tier = "keep-in-mind"
)

// Skill is one discipline, declared by its type.
type Skill interface {
	Definition() Definition
}

// Definition is what a skill states about itself.
type Definition struct {
	Slug                  string
	Tier                  Tier
	Order                 int
	Title                 string
	Trigger               string
	Intro                 string
	Summary               string
	Principle             string
	ExamplesKeepDocblocks bool
	Languages             []string
	Related               []Relation
	References            []Reference
}

// Reference is a page of detail published beside a skill.
type Reference struct {
	Name  string
	Title string
	Body  string
}

// Relation is a skill another one points its reader on to, by slug, with the one line that says why.
type Relation struct {
	Slug string
	Note string
}

var skills catalog.Catalog[Skill]

func Register(engine catalog.Engine, skill Skill) {
	skills.Register(engine, skill)
}

func All() []Skill {
	return skills.All()
}

func Every(engine catalog.Engine) []Skill {
	return skills.Every(engine)
}

func Of(engine catalog.Engine) []Skill {
	return skills.Of(engine)
}

func InTier(tier Tier) []Skill {
	var inTier []Skill
	for _, skill := range Ordered() {
		if skill.Definition().Tier == tier {
			inTier = append(inTier, skill)
		}
	}

	return inTier
}

func Ordered() []Skill {
	ordered := slices.Clone(All())
	slices.SortStableFunc(ordered, func(a, b Skill) int { return a.Definition().Order - b.Definition().Order })

	return ordered
}

func Slugged(slug string) (Skill, bool) {
	for _, slug := range []string{slug, renamed(slug)} {
		for _, skill := range All() {
			if slug != "" && skill.Definition().Slug == slug {
				return skill, true
			}
		}
	}

	return nil, false
}

func renamed(slug string) string {
	was, subject, prefixed := strings.Cut(slug, "/")
	if !prefixed {
		return ""
	}

	engine, known := catalog.EngineNamed(was)
	if !known || string(engine) == was {
		return ""
	}

	return string(engine) + "/" + subject
}

func IDFor(slug string) string {
	return "commandments-" + strings.ReplaceAll(slug, "/", "-")
}

func (d Definition) ID() string {
	return IDFor(d.Slug)
}

func (d Definition) Bullet() string {
	return "- **`" + d.ID() + "`** — " + d.Summary
}

func (d Definition) Matches(query string) bool {
	return strings.Contains(catalog.Normalise(d.Slug), catalog.Normalise(query))
}
