package library

import (
	"bytes"
	_ "embed"
	"encoding/json"
	"github.com/jessegall/code-commandments/catalog"
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
	"(`judge`, `info`, `report`, `make`). Load this when you start work in this " +
	"codebase, when you need to know WHICH discipline covers a subject you are about to write, " +
	"or when a `judge` finding names a rule you do not recognise."

const mapTriggerWithoutCommands = "The index of this project's architectural disciplines — every " +
	"code-commandments skill and when each one fires. Load this when you start work in this " +
	"codebase, or when you need to know WHICH discipline covers a subject you are about to write."

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
	holds := HeldIn(root, project)

	return strings.NewReplacer(
		"{{library}}", Dir,
		Binary, binary.Invocation(root),
		Judging, binary.Judging(root),
		"{{finding}}", said.finding,
		"{{mandatory}}", bullets(skill.Mandatory, holds, own),
		"{{keepInMind}}", bullets(skill.KeepInMind, holds, own),
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

func bullets(tier skill.Tier, holds func(source.Language) bool, own []custom.Skill) string {
	var lines []string

	for _, each := range Written(skill.InTier(tier), holds) {
		lines = append(lines, each.Definition().Bullet())
	}

	for _, each := range own {
		if each.Definition().Tier == tier && len(Written([]skill.Skill{each}, holds)) > 0 {
			lines = append(lines, each.Definition().Bullet()+ownMark)
		}
	}

	return strings.Join(lines, "\n")
}

func HeldIn(root string, project config.Config) func(source.Language) bool {
	known := map[source.Language]bool{}

	return func(language source.Language) bool {
		held, asked := known[language]
		if !asked {
			held = project.Holds(root, language)
			known[language] = held
		}

		return held
	}
}

func Written(skills []skill.Skill, holds func(source.Language) bool) []skill.Skill {
	var kept []skill.Skill

	for _, each := range skills {
		if engine, _ := skill.EngineOf(each); engine == catalog.Shared {
			kept = append(kept, each)

			continue
		}
		for _, name := range each.Definition().Languages {
			if language, known := LanguageOf(name); known && holds(language) {
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

func KeepSections(text string, holds func(source.Language) bool) string {
	var kept []string
	dropping := false

	for _, line := range strings.Split(text, "\n") {
		switch {
		case strings.HasPrefix(line, "### "):
			language, named := source.NamedIn(line)
			dropping = named && !holds(language)
		case strings.HasPrefix(line, "## "):
			dropping = false
		}

		if !dropping {
			kept = append(kept, line)
		}
	}

	return strings.Join(kept, "\n")
}
