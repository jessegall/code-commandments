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

// The engines, in the order the curriculum lists them.
const (
	Backend    Engine = "backend"
	Frontend   Engine = "frontend"
	TypeScript Engine = "typescript"
	Python     Engine = "python"
	CSharp     Engine = "csharp"
)

// Engines is every engine, in curriculum order.
var Engines = []Engine{Backend, Frontend, TypeScript, Python, CSharp}

// Label is the engine's name as a heading prints it.
func (e Engine) Label() string {
	switch e {
	case Frontend:
		return "Frontend"
	case TypeScript:
		return "TypeScript"
	case Python:
		return "Python"
	case CSharp:
		return "C#"
	default:
		return "Backend"
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

// Name is a rule's short type name, the name a fixture marker and a report use: ArrayBagDetector.
func Name(rule any) string {
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
