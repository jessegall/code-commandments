package library

import (
	"bytes"
	_ "embed"
	"encoding/json"
	"github.com/jessegall/code-commandments/cli/binary"
	"slices"
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

const mapTriggerWithoutCommands = "The index of this project's architectural disciplines — every " +
	"code-commandments skill and when each one fires. Load this when you start work in this " +
	"codebase, when you need to know WHICH discipline covers a subject you are about to write, " +
	"or when a context compaction may have dropped the disciplines you loaded earlier."

const (
	commandsFrom = "<!-- commands -->\n"
	commandsTo   = "<!-- /commands -->\n"
)

//go:embed briefing.md
var briefing string

//go:embed brief.md
var brief string

const Binary = "{{binary}}"

const Judging = "{{judging}}"

// phrases is the phrases of the briefings that name a command, and what stands in their place where no command is named.
type phrases struct {
	andTheCommands string
	finding        string
}

var withCommands = phrases{andTheCommands: ", and the rest of\nthe commands", finding: "A `judge` finding"}

var withoutCommands = phrases{finding: "A finding"}

func Briefing(root string, project config.Config) string {
	return strings.NewReplacer(commandsFrom, "", commandsTo, "").Replace(renderedBriefing(root, project, withCommands))
}

func BriefingWithoutCommands(root string, project config.Config) string {
	return strings.TrimRight(droppingCommands(renderedBriefing(root, project, withoutCommands)), "\n")
}

func renderedBriefing(root string, project config.Config, said phrases) string {
	own := custom.InForce(root).Skills

	return strings.NewReplacer(
		"{{library}}", Dir,
		Binary, binary.Invocation(root),
		Judging, binary.Judging(root),
		"{{finding}}", said.finding,
		"{{mandatory}}", bullets(skill.Mandatory, project, own),
		"{{keepInMind}}", bullets(skill.KeepInMind, project, own),
	).Replace(briefing)
}

func droppingCommands(text string) string {
	for {
		from := strings.Index(text, commandsFrom)
		if from < 0 {
			return text
		}

		kept, rest := text[:from], ""
		if to := strings.Index(text[from:], commandsTo); to >= 0 {
			rest = text[from+to+len(commandsTo):]
		}

		if strings.HasSuffix(kept, "\n\n") {
			rest = strings.TrimPrefix(rest, "\n")
		}

		text = kept + rest
	}
}

func Brief(root string, project config.Config) string {
	return renderedBrief(binary.Invocation(root), binary.Judging(root), withCommands)
}

func BriefForJournal() string {
	return renderedBrief(binary.PluginAlias, binary.Alias, withCommands)
}

func BriefForWorkers() string {
	paragraphs := strings.Split(renderedBrief(binary.PluginAlias, binary.Alias, withoutCommands), "\n\n")

	return strings.Join(slices.DeleteFunc(paragraphs, func(paragraph string) bool {
		return strings.Contains(paragraph, binary.Alias) || strings.Contains(paragraph, binary.PluginAlias)
	}), "\n\n")
}

func renderedBrief(invocation, judging string, said phrases) string {
	return strings.NewReplacer(
		"{{library}}", Dir,
		Binary, invocation,
		Judging, judging,
		"{{andTheCommands}}", said.andTheCommands,
		"{{finding}}", said.finding,
	).Replace(brief)
}

func Map(root string, project config.Config) string {
	return mapOf(mapTrigger, Briefing(root, project))
}

func MapWithoutCommands(root string, project config.Config) string {
	return mapOf(mapTriggerWithoutCommands, BriefingWithoutCommands(root, project))
}

func mapOf(trigger, body string) string {
	var description bytes.Buffer

	encoder := json.NewEncoder(&description)
	encoder.SetEscapeHTML(false)
	_ = encoder.Encode(trigger)

	return "---\nname: " + MapID + "\ndescription: " + strings.TrimSuffix(description.String(), "\n") + "\n---\n\n" +
		"# Code Commandments — the disciplines in force here\n\n" +
		body + "\n"
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
