// Package make is `make`: scaffold a commandment of the project's own, a skill, a sin and a detector in
// `.commandments/custom/`, registered in the config, with the rest of the process printed.
package make

import (
	"regexp"
	"strings"

	"github.com/jessegall/code-commandments/skill"
)

// namespace is the namespace the project's own rules are declared in.
const namespace = "Commandments"

// Engine is the parse engine a new detector reads.
type Engine string

// The engines a commandment can be written for.
const (
	Backend  Engine = "backend"
	Frontend Engine = "frontend"
	Python   Engine = "python"
	CSharp   Engine = "csharp"
)

// Engines are every engine, in the order they are offered.
var Engines = []Engine{Backend, Frontend, Python, CSharp}

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
	if e == Frontend {
		return "resources/js"
	}

	return "src"
}

// ProbeExtension is the probe file's extension.
func (e Engine) ProbeExtension() string {
	switch e {
	case Frontend:
		return "vue"
	case Python:
		return "py"
	case CSharp:
		return "cs"
	default:
		return "php"
	}
}

// Blueprint is the three classes a commandment is made of, named and placed.
type Blueprint struct {
	Sin        string
	ID         string
	Engine     Engine
	Slug       string
	Skill      string
	SkillClass string
	Dir        string
}

// Of plans a commandment named name; with no skillClass it writes a skill of its own for the slug.
func Of(name string, engine Engine, slug, skillClass, dir string) Blueprint {
	sin := Studly(strings.TrimSuffix(Studly(name), "Detector"))
	blueprint := Blueprint{Sin: sin, ID: Kebab(sin), Engine: engine, Slug: slug, SkillClass: skillClass, Dir: dir}

	if skillClass == "" {
		blueprint.Skill = skillNamed(slug[strings.LastIndex(slug, "/")+1:], sin)
		blueprint.SkillClass = namespace + `\` + blueprint.Skill
	}

	return blueprint
}

// Detector is the detector's class name.
func (b Blueprint) Detector() string {
	return b.Sin + "Detector"
}

// DetectorClass is the detector's full class.
func (b Blueprint) DetectorClass() string {
	return namespace + `\` + b.Detector()
}

// File is one class the blueprint writes.
type File struct {
	Path  string
	Class string
}

// Files are the classes it writes: the skill first when it writes one, then the sin and the detector.
func (b Blueprint) Files() []File {
	var files []File

	if b.Skill != "" {
		files = append(files, File{b.Dir + "/" + b.Skill + ".php", b.Skill})
	}

	return append(files, File{b.Dir + "/" + b.Sin + ".php", b.Sin}, File{b.Dir + "/" + b.Detector() + ".php", b.Detector()})
}

// SkillID is the id the skill is published under.
func (b Blueprint) SkillID() string {
	return skill.IDFor(b.Slug)
}

func skillNamed(stem, sin string) string {
	if named := Studly(stem); named != sin {
		return named
	}

	return Studly(stem) + "Skill"
}

var (
	nonWord       = regexp.MustCompile(`[^A-Za-z0-9]+`)
	lowerToUpper  = regexp.MustCompile(`([a-z0-9])([A-Z])`)
	acronymToWord = regexp.MustCompile(`([A-Z]+)([A-Z][a-z])`)
)

// Studly is the name as a class name: its words, each capitalised, run together.
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
