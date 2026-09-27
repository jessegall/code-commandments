package library

import (
	"bytes"
	_ "embed"
	"encoding/json"
	"github.com/jessegall/code-commandments/cli/binary"
	"strings"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/custom"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/skill"
)

// MapID is the id of the skill that maps every other: the briefing, loadable like a discipline.
const MapID = "commandments"

// mapTrigger says when to load the map: the moments a reader needs every discipline rather than one.
const mapTrigger = "The index of this project's architectural disciplines — every " +
	"code-commandments skill, when each one fires, and the commands that find and fix sins " +
	"(`judge`, `info`, `repent`, `report`, `make`). Load this when you start work in this " +
	"codebase, when you need to know WHICH discipline covers a subject you are about to write, " +
	"when a `judge` finding names a rule you do not recognise, or when a context compaction may " +
	"have dropped the disciplines you loaded earlier."

//go:embed briefing.md
var briefing string

// Briefing is the canon every agent in the project at root is handed: how the skills are loaded, the rule
// they all serve, and the disciplines the project can use, tier by tier, its own among them.
func Briefing(root string, project config.Config) string {
	own := custom.Load(root).Skills

	return strings.NewReplacer(
		"{{library}}", Dir,
		"{{binary}}", binary.Invocation(root),
		"{{mandatory}}", bullets(skill.Mandatory, project, own),
		"{{keepInMind}}", bullets(skill.KeepInMind, project, own),
	).Replace(briefing)
}

// Map is the SKILL.md of the map skill: the briefing under a front matter that says when to load it.
func Map(root string, project config.Config) string {
	var description bytes.Buffer

	encoder := json.NewEncoder(&description)
	encoder.SetEscapeHTML(false)
	_ = encoder.Encode(mapTrigger)

	return "---\nname: " + MapID + "\ndescription: " + strings.TrimSuffix(description.String(), "\n") + "\n---\n\n" +
		"# Code Commandments — the disciplines in force here\n\n" +
		Briefing(root, project) + "\n"
}

// ownMark follows the bullet of a skill the project wrote itself.
const ownMark = " _(this project's own — `.commandments/custom/`)_"

func bullets(tier skill.Tier, project config.Config, own []custom.Skill) string {
	var lines []string

	for _, each := range Written(skill.InTier(tier), project) {
		lines = append(lines, each.Definition().Bullet())
	}

	for _, each := range own {
		if each.Definition().Tier == tier && len(Written([]skill.Skill{each}, project)) > 0 {
			lines = append(lines, each.Definition().Bullet()+ownMark)
		}
	}

	return strings.Join(lines, "\n")
}

// Written are the skills the project can use: those teaching at least one language it writes. A discipline
// whose every language is disabled is one the project cannot break, so it is never briefed nor published.
func Written(skills []skill.Skill, project config.Config) []skill.Skill {
	var kept []skill.Skill

	for _, each := range skills {
		for _, name := range each.Definition().Languages {
			if language, known := LanguageOf(name); known && project.Writes(language) {
				kept = append(kept, each)

				break
			}
		}
	}

	return kept
}

// LanguageOf reads a skill's language: its source value (`py`) or its config name (`python`).
func LanguageOf(name string) (source.Language, bool) {
	for _, language := range source.Languages {
		if string(language) == name || strings.EqualFold(language.Label(), name) || config.LanguageName(language) == name {
			return language, true
		}
	}

	return "", false
}

// KeepSections is a skill with every `### … — in <Language>` example dropped for a language the project
// does not write; a `## ` section always resumes.
func KeepSections(text string, project config.Config) string {
	if len(project.DisabledLanguages) == 0 {
		return text
	}

	var kept []string
	dropping := false

	for _, line := range strings.Split(text, "\n") {
		switch {
		case strings.HasPrefix(line, "### "):
			language, named := source.NamedIn(line)
			dropping = named && !project.Writes(language)
		case strings.HasPrefix(line, "## "):
			dropping = false
		}

		if !dropping {
			kept = append(kept, line)
		}
	}

	return strings.Join(kept, "\n")
}
