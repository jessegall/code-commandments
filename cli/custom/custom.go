// Package custom reads a project's own commandments from .commandments/custom/: its skills, each a folder
// holding a SKILL.md, and its detectors, each a rule file. They are the same Skill and Detector the tool
// ships, named as the project's own wherever they appear.
package custom

import (
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/skill"
)

// SkillsFolder holds the project's own skills, a folder each.
const SkillsFolder = "skills"

// order places a project's own skills after every shipped one in a briefing.
const order = 1_000_000

// Skill is one of the project's own skills: a folder holding its SKILL.md, whose front matter names it.
type Skill struct {
	Dir        string
	definition skill.Definition
}

// Definition is what the skill says about itself.
func (s Skill) Definition() skill.Definition {
	return s.definition
}

// Project is what a project keeps in its custom folder.
type Project struct {
	Skills []Skill
	Rules  []rule.Rule
	// Unreadable are the rule files the tool cannot run, each with the reason.
	Unreadable []string
	// Classes are PHP classes left from the PHP tool, which the binary cannot run, under the project.
	Classes []string
}

// Load reads the custom folder of the project at root.
func Load(root string) Project {
	dir := workspace.CustomDir(root)
	var project Project

	skills, _ := filepath.Glob(filepath.Join(dir, SkillsFolder, "*", "SKILL.md"))
	sort.Strings(skills)

	for _, path := range skills {
		if own, read := readSkill(path); read {
			project.Skills = append(project.Skills, own)
		}
	}

	rules, _ := filepath.Glob(filepath.Join(dir, "*.json"))
	sort.Strings(rules)

	for _, path := range rules {
		text, err := os.ReadFile(path)
		if err != nil {
			continue
		}

		read, err := rule.Parse(strings.TrimSuffix(filepath.Base(path), ".json"), text, project.skill)
		if err != nil {
			project.Unreadable = append(project.Unreadable, err.Error())

			continue
		}

		project.Rules = append(project.Rules, read)
	}

	for _, class := range workspace.CustomFiles(root) {
		if relative, err := filepath.Rel(root, class); err == nil {
			class = relative
		}

		project.Classes = append(project.Classes, class)
	}

	return project
}

// skill is the skill a slug names: the project's own first, then a shipped one.
func (p Project) skill(slug string) (skill.Skill, bool) {
	for _, own := range p.Skills {
		if own.definition.Slug == slug {
			return own, true
		}
	}

	return skill.Slugged(slug)
}

// Enabled are the project's rules its config turns on, less any it disables.
func (p Project) Enabled(project config.Config) []detectors.Detector {
	var enabled []detectors.Detector

	for _, own := range p.Rules {
		if slices.Contains(project.Detectors, own.Name()) && !project.Disables(config.Rule{Kind: config.Detector, Name: own.Name()}) {
			enabled = append(enabled, own)
		}
	}

	return enabled
}

// Warnings are what a run says about the custom folder before it judges: rules the config turns on that are
// not there or cannot run, and PHP classes the binary skips.
func (p Project) Warnings(project config.Config) []string {
	var missing []string

	for _, name := range project.Detectors {
		if !slices.ContainsFunc(p.Rules, func(own rule.Rule) bool { return own.Name() == name }) {
			missing = append(missing, name)
		}
	}

	var warnings []string

	if len(missing) > 0 {
		warnings = append(warnings, fmt.Sprintf("⚠ %d configured detector(s) could not be loaded, and were skipped:\n  %s\n"+
			"  A project's own rules live in .commandments/custom/ as <Name>.json — check they are present and committed.",
			len(missing), strings.Join(missing, "\n  ")))
	}

	for _, reason := range p.Unreadable {
		warnings = append(warnings, "⚠ .commandments/custom/"+reason)
	}

	for _, class := range p.Classes {
		warnings = append(warnings, "⚠ "+class+" is a PHP class the binary cannot run, and was skipped — "+
			"write it as a rule with `commandments make`, then delete the class.")
	}

	return warnings
}

// Owns says whether the detector is one of the project's own.
func Owns(detector detectors.Detector) bool {
	_, own := detector.(rule.Rule)

	return own
}

// readSkill reads a skill's front matter: name, description, summary, tier and the languages it teaches, by
// default every one.
func readSkill(path string) (Skill, bool) {
	text, err := os.ReadFile(path)
	if err != nil {
		return Skill{}, false
	}

	fields := frontMatter(string(text))
	tier := skill.KeepInMind

	if fields["tier"] == string(skill.Mandatory) {
		tier = skill.Mandatory
	}

	var languages []string
	for _, language := range source.Languages {
		languages = append(languages, string(language))
	}

	if listed := fields["languages"]; listed != "" {
		languages = nil

		for _, language := range strings.Split(strings.Trim(listed, "[]"), ",") {
			languages = append(languages, strings.Trim(strings.TrimSpace(language), `"'`))
		}
	}

	summary := fields["summary"]
	if summary == "" {
		summary = fields["description"]
	}

	return Skill{filepath.Dir(path), skill.Definition{
		Slug:      filepath.Base(filepath.Dir(path)),
		Tier:      tier,
		Order:     order,
		Title:     fields["name"],
		Trigger:   fields["description"],
		Summary:   summary,
		Languages: languages,
	}}, true
}

// frontMatter are the `key: value` lines between a document's opening `---` lines.
func frontMatter(text string) map[string]string {
	fields := map[string]string{}
	lines := strings.Split(strings.ReplaceAll(text, "\r\n", "\n"), "\n")

	if len(lines) == 0 || strings.TrimSpace(lines[0]) != "---" {
		return fields
	}

	for _, line := range lines[1:] {
		if strings.TrimSpace(line) == "---" {
			break
		}

		if key, value, found := strings.Cut(line, ":"); found {
			fields[strings.TrimSpace(key)] = strings.Trim(strings.TrimSpace(value), `"`)
		}
	}

	return fields
}
