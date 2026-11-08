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

const MapID = "commandments"

const mapTrigger = "The index of this project's architectural disciplines — every " +
	"code-commandments skill, when each one fires, and the commands that find and fix sins " +
	"(`judge`, `info`, `repent`, `report`, `make`). Load this when you start work in this " +
	"codebase, when you need to know WHICH discipline covers a subject you are about to write, " +
	"when a `judge` finding names a rule you do not recognise, or when a context compaction may " +
	"have dropped the disciplines you loaded earlier."

//go:embed briefing.md
var briefing string

//go:embed brief.md
var brief string

const Binary = "{{binary}}"

const Judging = "{{judging}}"

func Briefing(root string, project config.Config) string {
	own := custom.InForce(root).Skills

	return strings.NewReplacer(
		"{{library}}", Dir,
		Binary, binary.Invocation(root),
		Judging, binary.Judging(root),
		"{{mandatory}}", bullets(skill.Mandatory, project, own),
		"{{keepInMind}}", bullets(skill.KeepInMind, project, own),
	).Replace(briefing)
}

func Brief(root string, project config.Config) string {
	return renderedBrief(binary.Invocation(root), binary.Judging(root))
}

func BriefForJournal() string {
	return renderedBrief(binary.PluginAlias, binary.Alias)
}

func renderedBrief(invocation, judging string) string {
	return strings.NewReplacer(
		"{{library}}", Dir,
		Binary, invocation,
		Judging, judging,
	).Replace(brief)
}

func Map(root string, project config.Config) string {
	var description bytes.Buffer

	encoder := json.NewEncoder(&description)
	encoder.SetEscapeHTML(false)
	_ = encoder.Encode(mapTrigger)

	return "---\nname: " + MapID + "\ndescription: " + strings.TrimSuffix(description.String(), "\n") + "\n---\n\n" +
		"# Code Commandments — the disciplines in force here\n\n" +
		Briefing(root, project) + "\n"
}

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

func LanguageOf(name string) (source.Language, bool) {
	for _, language := range source.Languages {
		if string(language) == name || strings.EqualFold(language.Label(), name) || config.LanguageName(language) == name {
			return language, true
		}
	}

	return "", false
}

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
