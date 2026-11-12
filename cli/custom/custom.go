// Package custom reads a project's own commandments from .commandments/custom/: its skills, each a folder holding a SKILL.md, and its detectors, each a rule file.
package custom

import (
	"fmt"
	"github.com/jessegall/code-commandments/walk"
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

const SkillsFolder = "skills"

const order = 1_000_000

// Skill is one of the project's own skills: a folder holding its SKILL.md, whose front matter names it.
type Skill struct {
	Dir        string
	definition skill.Definition
}

func (s Skill) Definition() skill.Definition {
	return s.definition
}

// Project is what a project keeps in its custom folder.
type Project struct {
	Skills     []Skill
	Rules      []rule.Rule
	Unreadable []string
	Refused    []string
	Classes    []string
}

func InForce(dir string) Project {
	return Load(workspace.SettingsRoot(dir))
}

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

func jsonUnder(dir string) (rules, lists []string) {
	for _, path := range walk.Files(dir, walk.Excluded{}, func(path string) bool {
		inside, _ := filepath.Rel(dir, path)

		return strings.HasSuffix(path, ".json") && !slices.Contains(strings.Split(filepath.ToSlash(inside), "/"), SkillsFolder)
	}) {
		if filepath.Base(path) == ListsFile {
			lists = append(lists, path)
		} else {
			rules = append(rules, path)
		}
	}

	sort.Strings(rules)
	sort.Strings(lists)

	return rules, lists
}

func RuleFiles(dir string) []string {
	rules, _ := jsonUnder(dir)

	return rules
}

func (p Project) skill(slug string) (skill.Skill, bool) {
	for _, own := range p.Skills {
		if own.definition.Slug == slug {
			return own, true
		}
	}

	return skill.Slugged(slug)
}

func (p Project) Named(name string) (rule.Rule, bool) {
	wanted := catalog.Normalise(strings.TrimSuffix(name, "Detector"))
	for _, own := range p.Rules {
		if catalog.Normalise(strings.TrimSuffix(own.Name(), "Detector")) == wanted {
			return own, true
		}
	}

	return rule.Rule{}, false
}

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

func (p Project) Enabled(project config.Config) []detectors.Detector {
	var enabled []detectors.Detector

	for _, own := range p.Rules {
		if slices.Contains(project.Detectors, own.Name()) && !project.Disables(config.Rule{Kind: config.Detector, Name: own.Name()}) {
			enabled = append(enabled, own.WithLayers(project.Layers(own.Engine())))
		}
	}

	return enabled
}

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

func (p Project) ClassWarnings() []string {
	var warnings []string

	for _, class := range p.Classes {
		warnings = append(warnings, "⚠ "+class+" is a PHP class the binary cannot run, and was skipped — "+
			"a detector and its sin become a rule (`commandments make`), a skill a folder holding a SKILL.md; "+
			"then delete the class. docs/parity.md, \"Migrating from the PHP tool\", says how, and what an agent class becomes.")
	}

	return warnings
}

func Owns(detector detectors.Detector) bool {
	_, own := detector.(rule.Rule)

	return own
}

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
