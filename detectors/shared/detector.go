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
	"github.com/jessegall/code-commandments/engine/php/packages"
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
type Sin struct {
	rule Rule
	name string
}

func SinOf(rule Rule) Sin {
	return Sin{rule: rule, name: reflect.TypeOf(rule).Name()}
}

func (s Sin) Definition() sins.Definition {
	text := s.rule.Sin()

	return sins.Definition{
		Name:        text.Name,
		Skill:       text.Skill,
		Description: text.Description,
		Rule:        text.Rule,
		Suggestion:  text.Suggestion,
		Formerly:    formerNames(text, func(former Former) string { return former.Sin }),
	}
}

func (s Sin) Name() string {
	return s.name
}

func (s Sin) Engine() catalog.Engine {
	return catalog.Shared
}

func (s Sin) FormerNames() []string {
	return formerNames(s.rule.Sin(), func(former Former) string { return strings.TrimSuffix(former.Detector, "Detector") })
}

func (s Sin) Published() bool {
	return !catalog.IsUnpublished(s.rule)
}

// Detector judges one language by a shared rule and names what it finds by that rule's one sin.
type Detector struct {
	rule     Rule
	sin      Sin
	language Language
	name     string
	binding
}

// Option is what a language states about its binding of a shared rule.
type Option func(*binding)

type binding struct {
	unpublished bool
	excused     func(*engine.Codebase, engine.Match) bool
	exemptions  []packages.Exemption
	reject      engine.Check
}

// CandidateRule can apply an exemption before evaluating a group of candidates.
type CandidateRule interface {
	FindRejecting(*engine.Codebase, engine.Check) []engine.Match
}

var registered sync.Map

func Bind[R Rule, L Language](options ...Option) Detector {
	return register(bound[R, L](options))
}

// TracedRule can describe the value flow behind a finding.
type TracedRule interface {
	Rule
	ChainPath(engine.Match, *engine.Codebase) []string
}

// TracedDetector attaches a rule's value-flow evidence to its findings.
type TracedDetector struct {
	Detector
	traced TracedRule
}

// GroupedRule is a shared rule whose findings recur in groups: the key says which group a finding is in, the same in every language.
type GroupedRule interface {
	Rule
	GroupKey(match engine.Match) (string, bool)
}

// GroupedDetector judges one language by a grouped shared rule, so a report names each finding's twins.
type GroupedDetector struct {
	Detector
	grouped GroupedRule
}

func BindGrouped[R GroupedRule, L Language](options ...Option) GroupedDetector {
	var rule R

	return register(GroupedDetector{bound[R, L](options), rule})
}

func (d GroupedDetector) GroupKey(match engine.Match) (string, bool) {
	return d.grouped.GroupKey(match)
}

func bound[R Rule, L Language](options []Option) Detector {
	var rule R
	var language L

	return bind(rule, language, options)
}

func bind(rule Rule, language Language, options []Option) Detector {
	var settings binding
	for _, option := range options {
		option(&settings)
	}
	sin := SinOf(rule)
	if _, already := registered.LoadOrStore(reflect.TypeOf(rule), true); !already {
		sins.Register(catalog.Shared, sin)
	}

	return Detector{rule: rule, sin: sin, language: language, name: catalog.Name(sin) + "Detector", binding: settings}
}

func register[D interface {
	detectors.Detector
	detectors.Engined
}](detector D) D {
	detectors.Register(detector.Engine(), detector)
	return detector
}

func (d Detector) Sin() sins.Sin {
	return d.sin
}

func (d Detector) Find(codebase *engine.Codebase) []engine.Match {
	part := codebase.Of(catalog.LanguagesOf(d.Engine())...)
	if candidates, ok := d.rule.(CandidateRule); ok && d.reject != nil {
		return candidates.FindRejecting(part, d.reject)
	}
	found := d.rule.Find(part)
	if d.excused == nil {
		return found
	}

	return slices.DeleteFunc(found, func(finding engine.Match) bool { return d.excused(codebase, finding) })
}

func (d Detector) Exemptions() []packages.Exemption {
	return d.exemptions
}

func (d Detector) Name() string {
	return d.name
}

func (d Detector) FormerNames() []string {
	if name := d.rule.Sin().Formerly[d.Engine()].Detector; name != "" {
		return []string{name}
	}

	return nil
}

func (d Detector) Language() any {
	return d.language
}

func (d Detector) Engine() catalog.Engine {
	return d.language.Engine()
}

func (d Detector) Published() bool {
	return !d.unpublished && !catalog.IsUnpublished(d.rule)
}

func (d Detector) ReadsAcrossFiles() bool {
	return catalog.IsCrossFile(d.rule)
}

func (d Detector) ReadsForward() bool {
	return catalog.IsForward(d.rule)
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
