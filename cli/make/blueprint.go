// Package make is `make`: scaffold a commandment of the project's own in `.commandments/custom/` (a rule
// naming its sin, and the skill that teaches the fix when it is a new one), turned on in the config, with the
// rest of the process printed.
package make

import (
	"path/filepath"
	"regexp"
	"strings"

	"github.com/jessegall/code-commandments/skill"
)

// Engine is the engine a new rule judges.
type Engine string

// The engines a commandment can be written for.
const (
	Backend    Engine = "backend"
	Frontend   Engine = "frontend"
	TypeScript Engine = "typescript"
	Python     Engine = "python"
	CSharp     Engine = "csharp"
)

// Engines are every engine, in the order they are offered.
var Engines = []Engine{Backend, Frontend, TypeScript, Python, CSharp}

// ParseEngine reads an engine by name, whatever its case.
func ParseEngine(name string) (Engine, bool) {
	for _, engine := range Engines {
		if string(engine) == strings.ToLower(name) {
			return engine, true
		}
	}

	return "", false
}

// ProbeRoot is where a throwaway probe for the engine goes.
func (e Engine) ProbeRoot() string {
	if e == Frontend || e == TypeScript {
		return "resources/js"
	}

	return "src"
}

// ProbeExtension is the probe file's extension, which is also the language its skill teaches.
func (e Engine) ProbeExtension() string {
	switch e {
	case Frontend:
		return "vue"
	case TypeScript:
		return "ts"
	case Python:
		return "py"
	case CSharp:
		return "cs"
	default:
		return "php"
	}
}

// Blueprint is a commandment named and placed: its sin, the engine its rule judges, and the skill that
// teaches the fix, written anew when no existing one was named.
type Blueprint struct {
	Sin      string
	ID       string
	Engine   Engine
	Slug     string
	NewSkill bool
	Dir      string
}

// Of plans a commandment named name, taught by the skill slug; newSkill writes that skill too.
func Of(name string, engine Engine, slug string, newSkill bool, dir string) Blueprint {
	sin := Studly(strings.TrimSuffix(Studly(name), "Detector"))

	return Blueprint{Sin: sin, ID: Kebab(sin), Engine: engine, Slug: slug, NewSkill: newSkill, Dir: dir}
}

// Detector is the rule's name.
func (b Blueprint) Detector() string {
	return b.Sin + "Detector"
}

// File is one file the blueprint writes.
type File struct {
	Path string
	Is   string
}

// Files are what it writes: the skill first when it writes one, then the rule.
func (b Blueprint) Files() []File {
	var files []File

	if b.NewSkill {
		files = append(files, File{b.SkillFile(), "the skill `" + b.Slug + "`"})
	}

	return append(files, File{b.RuleFile(), "the rule, and its sin `" + b.ID + "`"})
}

// SkillFile is where a new skill's SKILL.md goes.
func (b Blueprint) SkillFile() string {
	return filepath.Join(b.Dir, "skills", b.Slug, "SKILL.md")
}

// RuleFile is where the rule goes.
func (b Blueprint) RuleFile() string {
	return filepath.Join(b.Dir, b.Detector()+".json")
}

// SkillID is the id the skill is published under.
func (b Blueprint) SkillID() string {
	return skill.IDFor(b.Slug)
}

var (
	nonWord       = regexp.MustCompile(`[^A-Za-z0-9]+`)
	lowerToUpper  = regexp.MustCompile(`([a-z0-9])([A-Z])`)
	acronymToWord = regexp.MustCompile(`([A-Z]+)([A-Z][a-z])`)
)

// Studly is the name as a type name: its words, each capitalised, run together.
func Studly(name string) string {
	var studly strings.Builder

	for _, word := range nonWord.Split(name, -1) {
		if word != "" {
			studly.WriteString(strings.ToUpper(word[:1]) + word[1:])
		}
	}

	return studly.String()
}

// Kebab is the name as an id: words split at case changes, lowercased, joined by dashes.
func Kebab(name string) string {
	spaced := acronymToWord.ReplaceAllString(lowerToUpper.ReplaceAllString(name, "$1-$2"), "$1-$2")

	return strings.ToLower(nonWord.ReplaceAllString(strings.Trim(spaced, "-"), "-"))
}
