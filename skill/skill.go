// Package skill holds the teaching layer's metadata: each Skill names a discipline, and a sin
// points at the skill that fixes it.
package skill

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
)

// Tier says how often a skill comes up: constantly in play, or on contact with its subject.
type Tier string

// The tiers, in the order the briefing lists them.
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

// Register enrols a skill under its engine, from the skill's own file.
func Register(engine catalog.Engine, skill Skill) {
	skills.Register(engine, skill)
}

// All is every published skill.
func All() []Skill {
	return skills.All()
}

// Every is every skill registered under one engine, unpublished ones included.
func Every(engine catalog.Engine) []Skill {
	return skills.Every(engine)
}

// Of is every published skill of one engine.
func Of(engine catalog.Engine) []Skill {
	return skills.Of(engine)
}

// InTier is every published skill of a tier, in its order.
func InTier(tier Tier) []Skill {
	var inTier []Skill
	for _, skill := range All() {
		if skill.Definition().Tier == tier {
			inTier = append(inTier, skill)
		}
	}
	slices.SortStableFunc(inTier, func(a, b Skill) int { return a.Definition().Order - b.Definition().Order })

	return inTier
}

// Slugged is the published skill with the slug.
func Slugged(slug string) (Skill, bool) {
	for _, skill := range All() {
		if skill.Definition().Slug == slug {
			return skill, true
		}
	}

	return nil, false
}

// IDFor is the id a skill with the slug is loaded by: commandments-backend-absence.
func IDFor(slug string) string {
	return "commandments-" + strings.ReplaceAll(slug, "/", "-")
}

// ID is the id the skill is loaded by.
func (d Definition) ID() string {
	return IDFor(d.Slug)
}

// Bullet is the skill's line in a briefing list.
func (d Definition) Bullet() string {
	return "- **`" + d.ID() + "`** — " + d.Summary
}

// Matches says whether a query names the skill, leniently.
func (d Definition) Matches(query string) bool {
	return strings.Contains(catalog.Normalise(d.Slug), catalog.Normalise(query))
}
