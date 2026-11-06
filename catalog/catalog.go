// Package catalog is the one enrolment mechanism every kind of rule shares: a sin, a detector and a skill each register themselves from their own file, and a catalog lists them.
package catalog

import (
	"fmt"
	"reflect"
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
)

// Unpublished marks a rule still being calibrated.
type Unpublished interface {
	Unpublished()
}

// CrossFile marks a rule that reads MORE than the file it reports in: a recurrence across sites, a twin, a caller, what refers to a declaration, a layer stack, what another engine published.
type CrossFile interface {
	CrossFile()
}

// ReadsAcrossFiles is a rule that answers for ITSELF whether it reads more than the file it reports in, as a project's own rule does: nothing measures a rule a consumer wrote, so it answers from the checks its file writes.
type ReadsAcrossFiles interface {
	ReadsAcrossFiles() bool
}

func IsCrossFile(rule any) bool {
	if answers, states := rule.(ReadsAcrossFiles); states {
		return answers.ReadsAcrossFiles()
	}

	_, crossFile := rule.(CrossFile)

	return crossFile
}

// Engine is the part of the curriculum a rule belongs to.
type Engine string

const (
	PHP        Engine = "php"
	Vue        Engine = "vue"
	TypeScript Engine = "typescript"
	Python     Engine = "python"
	CSharp     Engine = "csharp"
	Go         Engine = "go"
)

var Engines = []Engine{PHP, Vue, TypeScript, Python, CSharp, Go}

var languages = map[Engine][]contract.Language{
	PHP:        {contract.PHP},
	Vue:        {contract.Vue, contract.TypeScript},
	TypeScript: {contract.Vue, contract.TypeScript},
	Python:     {contract.Python},
	CSharp:     {contract.CSharp},
	Go:         {contract.Go},
}

func LanguagesOf(engine Engine) []contract.Language {
	if named, known := EngineNamed(string(engine)); known {
		engine = named
	}

	return languages[engine]
}

func Reads(engine Engine, language contract.Language) bool {
	return slices.Contains(languages[engine], language)
}

var formerly = map[string]Engine{"backend": PHP, "frontend": Vue}

func FormerNames() []string {
	var names []string
	for _, engine := range Engines {
		for name, was := range formerly {
			if was == engine {
				names = append(names, name)
			}
		}
	}

	return names
}

func EngineNamed(name string) (Engine, bool) {
	if engine, was := formerly[strings.ToLower(name)]; was {
		return engine, true
	}

	engine := Engine(strings.ToLower(name))
	if slices.Contains(Engines, engine) {
		return engine, true
	}

	return "", false
}

func (e Engine) Label() string {
	switch e {
	case Vue:
		return "Vue"
	case TypeScript:
		return "TypeScript"
	case Python:
		return "Python"
	case CSharp:
		return "C#"
	default:
		return "PHP"
	}
}

// Catalog is every registered rule of one kind, each under its engine.
type Catalog[T any] struct {
	mutex   sync.Mutex
	entries []entry[T]
}

type entry[T any] struct {
	engine Engine
	rule   T
}

func (c *Catalog[T]) Register(engine Engine, rule T) {
	c.mutex.Lock()
	defer c.mutex.Unlock()
	for _, registered := range c.entries {
		if reflect.TypeOf(registered.rule) == reflect.TypeOf(rule) {
			panic(fmt.Sprintf("%s is registered twice", Name(rule)))
		}
	}
	c.entries = append(c.entries, entry[T]{engine, rule})
}

func (c *Catalog[T]) All() []T {
	var all []T
	for _, engine := range Engines {
		all = append(all, c.Of(engine)...)
	}

	return all
}

func (c *Catalog[T]) Of(engine Engine) []T {
	c.mutex.Lock()
	defer c.mutex.Unlock()
	var rules []T
	for _, registered := range c.entries {
		if registered.engine != engine || IsUnpublished(registered.rule) {
			continue
		}
		rules = append(rules, registered.rule)
	}
	slices.SortFunc(rules, func(a, b T) int { return strings.Compare(Name(a), Name(b)) })

	return rules
}

func (c *Catalog[T]) Every(engine Engine) []T {
	c.mutex.Lock()
	defer c.mutex.Unlock()
	var rules []T
	for _, registered := range c.entries {
		if registered.engine == engine {
			rules = append(rules, registered.rule)
		}
	}

	return rules
}

func (c *Catalog[T]) EngineOf(rule T) (Engine, bool) {
	c.mutex.Lock()
	defer c.mutex.Unlock()
	for _, registered := range c.entries {
		if reflect.TypeOf(registered.rule) == reflect.TypeOf(rule) && !IsUnpublished(rule) {
			return registered.engine, true
		}
	}

	return "", false
}

func IsUnpublished(rule any) bool {
	_, unpublished := rule.(Unpublished)

	return unpublished
}

func Name(rule any) string {
	if named, states := rule.(interface{ Name() string }); states {
		return named.Name()
	}

	kind := reflect.TypeOf(rule)
	for kind.Kind() == reflect.Pointer {
		kind = kind.Elem()
	}

	return kind.Name()
}

func Normalise(value string) string {
	var folded strings.Builder
	for _, r := range strings.ToLower(value) {
		if r >= 'a' && r <= 'z' || r >= '0' && r <= '9' {
			folded.WriteRune(r)
		}
	}

	return folded.String()
}
