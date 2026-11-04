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

	"github.com/jessegall/code-commandments/catalog"
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
	// Refused are the rules whose file was found and could not be read, by name. A configured rule among them is
	// not missing: its own reason is already said, and telling the reader to check it is present sends them
	// looking for a file that is there.
	Refused []string
	// Classes are PHP classes left from the PHP tool, which the binary cannot run, under the project.
	Classes []string
}

// InForce reads the project's own commandments as a RUN is judged by them: from the tree the settings come
// from, which for a worktree is the main checkout.
//
// The rules a run judges by and the config that turns them on have to be one tree's. Read apart, a worktree cut
// before a rule was committed is told that a configured detector is "declared nowhere" — and, far worse than the
// warning, that rule judges nothing there while every other rule still fires, so the run reads as a pass.
//
// A command that works ON a rule — scaffolding one, trying one, migrating the files — reads with Load instead,
// since the rule it is working on is the one in the folder it was run in.
func InForce(dir string) Project {
	return Load(workspace.SettingsRoot(dir))
}

// Load reads the custom folder of the project at root, exactly as given.
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

	rules, listFiles := jsonUnder(dir)

	lists, err := readLists(dir, listFiles)
	if err != nil {
		project.Unreadable = append(project.Unreadable, err.Error())
	}

	named := map[string]string{}

	for _, path := range rules {
		text, err := os.ReadFile(path)
		if err != nil {
			continue
		}

		// A rule is named by its FILE, whatever folder holds it, so moving one into a folder leaves every
		// mention of it in the config standing. Two files of one name would then be one rule with two bodies,
		// and which of them judged would depend on the order a folder is read in, so neither is read.
		name := strings.TrimSuffix(filepath.Base(path), ".json")
		if where, taken := named[name]; taken {
			project.Unreadable = append(project.Unreadable, fmt.Sprintf("%s: a rule named %s is read from %s as well, and a rule's name is its file's; rename one of the two",
				shortOf(dir, path), name, where))
			project.Refused = append(project.Refused, name)

			continue
		}

		named[name] = shortOf(dir, path)

		written, err := lists.Expand(name, text)
		if err != nil {
			project.Unreadable = append(project.Unreadable, err.Error())
			project.Refused = append(project.Refused, name)

			continue
		}

		read, err := rule.Parse(name, written, project.skill)
		if err != nil {
			project.Unreadable = append(project.Unreadable, err.Error())
			project.Refused = append(project.Refused, name)

			continue
		}

		project.Rules = append(project.Rules, read)
	}

	composed, refused := rule.Composed(project.Rules)
	project.Rules = composed
	for _, err := range refused {
		project.Unreadable = append(project.Unreadable, err.Error())
	}

	for _, class := range workspace.CustomFiles(root) {
		if relative, err := filepath.Rel(root, class); err == nil {
			class = relative
		}

		project.Classes = append(project.Classes, class)
	}

	return project
}

// jsonUnder are the JSON files of the custom folder: the rules, at any depth, and the lists files beside them.
//
// A rule is read wherever it sits, since a project with rules for several parts of its code groups them by what
// they protect — `core/` for its domain, `viewer/` for its interface — and one flat folder of twenty rules shows
// nothing about which part each one guards. The skills folder is left out: a skill is a folder holding a SKILL.md
// and whatever JSON it keeps beside it is its own business, not a rule.
func jsonUnder(dir string) (rules, lists []string) {
	filepath.WalkDir(dir, func(path string, entry os.DirEntry, err error) error {
		if err != nil {
			return nil
		}

		if entry.IsDir() {
			if path != dir && filepath.Base(path) == SkillsFolder {
				return filepath.SkipDir
			}

			return nil
		}

		switch {
		case filepath.Base(path) == ListsFile:
			lists = append(lists, path)
		case strings.HasSuffix(path, ".json"):
			rules = append(rules, path)
		}

		return nil
	})

	sort.Strings(rules)
	sort.Strings(lists)

	return rules, lists
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

// Named is the project's rule the name means, leniently: its own name, or that name less its Detector suffix,
// in any case.
func (p Project) Named(name string) (rule.Rule, bool) {
	wanted := catalog.Normalise(strings.TrimSuffix(name, "Detector"))
	for _, own := range p.Rules {
		if catalog.Normalise(strings.TrimSuffix(own.Name(), "Detector")) == wanted {
			return own, true
		}
	}

	return rule.Rule{}, false
}

// Read reads the rule file at the path, finding its sin's skill among the project's own and the shipped ones.
func (p Project) Read(path string) (rule.Rule, error) {
	text, err := os.ReadFile(path)
	if err != nil {
		return rule.Rule{}, err
	}

	read, err := rule.Parse(strings.TrimSuffix(filepath.Base(path), ".json"), text, p.skill)
	if err != nil {
		return rule.Rule{}, err
	}

	return rule.ComposedWith(read, p.Rules)
}

// Enabled are the project's rules its config turns on, less any it disables.
func (p Project) Enabled(project config.Config) []detectors.Detector {
	var enabled []detectors.Detector

	for _, own := range p.Rules {
		if slices.Contains(project.Detectors, own.Name()) && !project.Disables(config.Rule{Kind: config.Detector, Name: own.Name()}) {
			enabled = append(enabled, own.WithLayers(project.Layers(own.Engine())))
		}
	}

	return enabled
}

// Warnings are what a run says about the custom folder before it judges: rules the config turns on that are
// not there or cannot run, rules a skill says fire that are declared nowhere, and PHP classes the binary skips.
func (p Project) Warnings(project config.Config) []string {
	var missing []string

	for _, name := range project.Detectors {
		if slices.ContainsFunc(p.Rules, func(own rule.Rule) bool { return own.Name() == name }) || slices.Contains(p.Refused, name) {
			continue
		}

		missing = append(missing, name)
	}

	var warnings []string

	if len(missing) > 0 {
		warnings = append(warnings, fmt.Sprintf("⚠ %d configured detector(s) are declared nowhere, and judge nothing:\n  %s\n"+
			"  A project's own rules live in .commandments/custom/ as <Name>.json, in that folder or any folder under it — check they are present and committed.",
			len(missing), strings.Join(missing, "\n  ")))
	}

	for _, reason := range p.Unreadable {
		warnings = append(warnings, "⚠ .commandments/custom/"+reason)
	}

	warnings = append(warnings, p.ClaimWarnings()...)

	return append(warnings, p.ClassWarnings()...)
}

// ClassWarnings name each PHP class the PHP tool left in the custom folder — a detector, sin, skill or agent — which the
// binary skips.
func (p Project) ClassWarnings() []string {
	var warnings []string

	for _, class := range p.Classes {
		warnings = append(warnings, "⚠ "+class+" is a PHP class the binary cannot run, and was skipped — "+
			"a detector and its sin become a rule (`commandments make`), a skill a folder holding a SKILL.md; "+
			"then delete the class. docs/parity.md, \"Migrating from the PHP tool\", says how, and what an agent class becomes.")
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
