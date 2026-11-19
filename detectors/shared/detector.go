// Package shared holds the rules written once over the generic tree, each with its one sin, and the binding that lets a language judge one.
package shared

import (
	"reflect"
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
	"github.com/jessegall/code-commandments/skill"
)

// Language is what a language states about judging the shared rules: the engine it judges them under.
type Language interface {
	Engine() catalog.Engine
}

// Former is what a language called a shared rule before it was shared, so a config or a filter naming the old one still finds it.
type Former struct {
	Sin      string
	Detector string
}

// Text is a shared rule's sin, stated once for every language.
type Text struct {
	Name        string
	Skill       skill.Skill
	Description string
	Rule        string
	Suggestion  string
	Formerly    map[catalog.Engine]Former
}

// Rule is one concept found the same way in every language it applies to, over a codebase already narrowed to that language.
type Rule interface {
	Find(codebase *engine.Codebase) []engine.Match
	Sin() Text
}

// Sin is a shared rule's one sin, whatever language found it.
type Sin[R Rule] struct{}

func (Sin[R]) Definition() sins.Definition {
	var rule R
	text := rule.Sin()

	return sins.Definition{
		Name:        text.Name,
		Skill:       text.Skill,
		Description: text.Description,
		Rule:        text.Rule,
		Suggestion:  text.Suggestion,
		Formerly:    formerNames(text, func(former Former) string { return former.Sin }),
	}
}

func (Sin[R]) Name() string {
	return reflect.TypeFor[R]().Name()
}

func (Sin[R]) FormerNames() []string {
	var rule R

	return formerNames(rule.Sin(), func(former Former) string { return strings.TrimSuffix(former.Detector, "Detector") })
}

func (Sin[R]) Published() bool {
	var rule R

	return !catalog.IsUnpublished(rule)
}

// Detector judges one language by a shared rule and names what it finds by that rule's one sin.
type Detector[R Rule, L Language] struct {
	repentable bool
}

// Option is what a language states about its binding of a shared rule.
type Option func(*binding)

type binding struct {
	repentable bool
}

func Repentable(bound *binding) {
	bound.repentable = true
}

var registered sync.Map

func Bind[R Rule, L Language](options ...Option) Detector[R, L] {
	var bound binding
	for _, option := range options {
		option(&bound)
	}
	if _, already := registered.LoadOrStore(reflect.TypeFor[R](), true); !already {
		sins.Register(catalog.Shared, Sin[R]{})
	}
	var language L
	detector := Detector[R, L]{repentable: bound.repentable}
	detectors.Register(language.Engine(), detector)

	return detector
}

func (Detector[R, L]) Sin() sins.Sin {
	return Sin[R]{}
}

func (Detector[R, L]) Find(codebase *engine.Codebase) []engine.Match {
	var rule R
	var language L

	return rule.Find(codebase.Of(catalog.LanguagesOf(language.Engine())...))
}

func (Detector[R, L]) Name() string {
	return reflect.TypeFor[R]().Name() + "Detector"
}

func (Detector[R, L]) FormerNames() []string {
	var rule R
	var language L
	if name := rule.Sin().Formerly[language.Engine()].Detector; name != "" {
		return []string{name}
	}

	return nil
}

func (Detector[R, L]) Language() any {
	var language L

	return language
}

func (d Detector[R, L]) Repents() bool {
	return d.repentable
}

func (Detector[R, L]) Published() bool {
	var rule R

	return !catalog.IsUnpublished(rule)
}

func (Detector[R, L]) ReadsAcrossFiles() bool {
	var rule R

	return catalog.IsCrossFile(rule)
}

func (Detector[R, L]) ReadsForward() bool {
	var rule R

	return catalog.IsForward(rule)
}

func formerNames(text Text, part func(Former) string) []string {
	var names []string
	for _, engine := range catalog.Engines {
		if name := part(text.Formerly[engine]); name != "" && !slices.Contains(names, name) {
			names = append(names, name)
		}
	}

	return names
}
