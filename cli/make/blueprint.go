// Package make is `make`: scaffold a commandment of the project's own in `.commandments/custom/` (a rule naming its sin, and the skill that teaches the fix when it is a new one), turned on in the config, with the rest of the process printed.
package make

import (
	"path/filepath"
	"regexp"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/skill"
)

// Engine is the engine a new rule judges: the catalog's own, so a name is read in one place and an engine renamed is renamed once.
type Engine = catalog.Engine

var Engines = catalog.Engines

func ParseEngine(name string) (Engine, bool) {
	return catalog.EngineNamed(name)
}

func ProbeRoot(e Engine) string {
	if e == catalog.Vue || e == catalog.TypeScript {
		return "resources/js"
	}

	return "src"
}

func ProbeExtension(e Engine) string {
	switch e {
	case catalog.Vue:
		return "vue"
	case catalog.TypeScript:
		return "ts"
	case catalog.Python:
		return "py"
	case catalog.CSharp:
		return "cs"
	case catalog.Go:
		return "go"
	default:
		return "php"
	}
}

// Blueprint is a commandment named and placed: its sin, the engine its rule judges, and the skill that teaches the fix, written anew when no existing one was named.
type Blueprint struct {
	Sin      string
	ID       string
	Engine   Engine
	Slug     string
	NewSkill bool
	Dir      string
	From     *Template
}

func Of(name string, engine Engine, slug string, newSkill bool, dir string) Blueprint {
	sin := Studly(strings.TrimSuffix(Studly(name), "Detector"))

	return Blueprint{Sin: sin, ID: Kebab(sin), Engine: engine, Slug: slug, NewSkill: newSkill, Dir: dir}
}

func (b Blueprint) Detector() string {
	return b.Sin + "Detector"
}

// File is one file the blueprint writes.
type File struct {
	Path string
	Is   string
}

func (b Blueprint) Files() []File {
	var files []File

	if b.NewSkill {
		files = append(files, File{b.SkillFile(), "the skill `" + b.Slug + "`"})
	}

	return append(files, File{b.RuleFile(), "the rule, and its sin `" + b.ID + "`"})
}

func (b Blueprint) SkillFile() string {
	return filepath.Join(b.Dir, "skills", b.Slug, "SKILL.md")
}

func (b Blueprint) RuleFile() string {
	return filepath.Join(b.Dir, b.Detector()+".json")
}

func (b Blueprint) SkillID() string {
	return skill.IDFor(b.Slug)
}

var (
	nonWord       = regexp.MustCompile(`[^A-Za-z0-9]+`)
	lowerToUpper  = regexp.MustCompile(`([a-z0-9])([A-Z])`)
	acronymToWord = regexp.MustCompile(`([A-Z]+)([A-Z][a-z])`)
)

func Studly(name string) string {
	var studly strings.Builder

	for _, word := range nonWord.Split(name, -1) {
		if word != "" {
			studly.WriteString(strings.ToUpper(word[:1]) + word[1:])
		}
	}

	return studly.String()
}

func Kebab(name string) string {
	spaced := acronymToWord.ReplaceAllString(lowerToUpper.ReplaceAllString(name, "$1-$2"), "$1-$2")

	return strings.ToLower(nonWord.ReplaceAllString(strings.Trim(spaced, "-"), "-"))
}
