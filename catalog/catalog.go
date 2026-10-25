// Package catalog is the one enrolment mechanism every kind of rule shares: a sin, a detector and a
// skill each register themselves from their own file, and a catalog lists them.
package catalog

import (
	"fmt"
	"reflect"
	"slices"
	"strings"
	"sync"
)

// Unpublished marks a rule still being calibrated. Every catalog leaves it out, so it never reaches
// judge, the fixture verifier, the generated docs or a release.
type Unpublished interface {
	Unpublished()
}

// Engine is the part of the curriculum a rule belongs to.
type Engine string

// The engines, in the order the curriculum lists them. Each is named after the language it reads, which is what
// a finding, a config and a rule file say.
const (
	PHP        Engine = "php"
	Vue        Engine = "vue"
	TypeScript Engine = "typescript"
	Python     Engine = "python"
	CSharp     Engine = "csharp"
	Go         Engine = "go"
)

// Engines is every engine, in curriculum order.
var Engines = []Engine{PHP, Vue, TypeScript, Python, CSharp, Go}

// formerly are the names two engines were published under before they were named after their language. A config,
// a rule file, a filter and a skill slug a project wrote then is read the same way now, and always will be: what
// a user wrote is never made wrong by a name of ours changing.
var formerly = map[string]Engine{"backend": PHP, "frontend": Vue}

// FormerNames are the names an engine was published under before, in curriculum order: what a schema offers
// beside the names of today, and what a slip of the keyboard is measured against.
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

// EngineNamed is the engine a name means: the name it is published under, or the one it was published under
// before. Every name read from outside the binary comes through here, so there is one place that knows both.
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

// Label is the engine's name as a heading prints it.
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

// Register enrols a rule under its engine; a type registered twice is a mistake and panics.
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

// All is every published rule, engine by engine in curriculum order, then by name.
func (c *Catalog[T]) All() []T {
	var all []T
	for _, engine := range Engines {
		all = append(all, c.Of(engine)...)
	}

	return all
}

// Of is every published rule of one engine, by name.
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

// Every is every rule registered under one engine, unpublished ones included, in registration order.
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

// EngineOf is the engine a published rule was registered under.
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

// IsUnpublished says whether a rule is still being calibrated.
func IsUnpublished(rule any) bool {
	_, unpublished := rule.(Unpublished)

	return unpublished
}

// Name is a rule's name, as a fixture marker and a report use it: the one it states, as a project's own rule
// does, else its short type name, ArrayBagDetector.
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

// Normalise folds a name for lenient matching: lower case, letters and digits only.
func Normalise(value string) string {
	var folded strings.Builder
	for _, r := range strings.ToLower(value) {
		if r >= 'a' && r <= 'z' || r >= '0' && r <= '9' {
			folded.WriteRune(r)
		}
	}

	return folded.String()
}
