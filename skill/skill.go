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

// Definition is what a skill states about itself. Body is the whole lesson, written by hand, with one section for each
// sin the skill fixes; Formerly are the slugs of the skills it took the place of, which still find it.
type Definition struct {
	Slug       string
	Formerly   []string
	Body       string
	Tier       Tier
	Order      int
	Title      string
	Trigger    string
	Summary    string
	Languages  []string
	Related    []Relation
	References []Reference
}

// Reference is a page of detail published beside a skill.
type Reference struct {
	Name  string
	Title string
	Body  string
}

func (r Reference) Path() string {
	return "reference/" + r.Name + ".md"
}

func (r Reference) Link() string {
	return "- [" + r.Title + "](" + r.Path() + ")"
}

func (r Reference) Document() string {
	return "# " + r.Title + "\n\n" + strings.TrimSpace(r.Body) + "\n"
}

func (d Definition) ReferenceLinks() []string {
	links := make([]string, len(d.References))
	for at, reference := range d.References {
		links[at] = reference.Link()
	}

	return links
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

func Of(engine catalog.Engine) []Skill {
	return skills.Of(engine)
}

func EngineOf(taught Skill) (catalog.Engine, bool) {
	return skills.EngineOf(taught)
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
	for _, slug := range []string{slug, renamed(slug)} {
		for _, skill := range All() {
			if slug != "" && slices.Contains(skill.Definition().Formerly, slug) {
				return skill, true
			}
		}
	}

	return nil, false
}

func (d Definition) Opening() string {
	var said []string
	for _, line := range strings.Split(d.Body, "\n") {
		sentence, quoted := strings.CutPrefix(line, "> ")
		if !quoted || strings.HasPrefix(sentence, "Read this") {
			break
		}
		said = append(said, sentence)
	}

	return strings.Join(said, " ")
}

func (d Definition) Principle() string {
	_, after, found := strings.Cut(d.Body, "## The principle\n")
	if !found {
		return ""
	}
	section, _, _ := strings.Cut(after, "\n## ")

	return strings.TrimSpace(section)
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

func (d Definition) Slugs() []string {
	return append([]string{d.Slug}, d.Formerly...)
}

func (d Definition) Matches(query string) bool {
	return slices.ContainsFunc(d.Slugs(), func(slug string) bool {
		return strings.Contains(catalog.Normalise(slug), catalog.Normalise(query))
	})
}
