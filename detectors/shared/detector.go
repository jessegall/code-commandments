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
	repentable  bool
	unpublished bool
	excused     func(*engine.Codebase, engine.Match) bool
	exemptions  []packages.Exemption
	reject      engine.Check
}

// Option is what a language states about its binding of a shared rule.
type Option func(*binding)

type binding struct {
	repentable  bool
	unpublished bool
	excused     func(*engine.Codebase, engine.Match) bool
	exemptions  []packages.Exemption
	reject      engine.Check
}

func Rejecting(reject engine.Check, exemptions ...packages.Exemption) Option {
	return func(bound *binding) {
		bound.reject = reject
		bound.exemptions = exemptions
	}
}

// CandidateRule can apply an exemption before evaluating a group of candidates.
type CandidateRule interface {
	FindRejecting(*engine.Codebase, engine.Check) []engine.Match
}

func Repentable(bound *binding) {
	bound.repentable = true
}

func Unpublished(bound *binding) {
	bound.unpublished = true
}

// Excusing is a language's own exemption from a shared rule: the package tags it honours, and the finding they excuse.
func Excusing(excused func(*engine.Codebase, engine.Match) bool, exemptions ...packages.Exemption) Option {
	return func(bound *binding) {
		bound.excused = excused
		bound.exemptions = exemptions
	}
}

var registered sync.Map

func Bind[R Rule, L Language](options ...Option) Detector[R, L] {
	detector := bound[R, L](options)
	register[L](detector)

	return detector
}

// LastDetector runs a repair after the other fixes settle its source.
type LastDetector[R Rule, L Language] struct {
	Detector[R, L]
}

func BindLast[R Rule, L Language](options ...Option) LastDetector[R, L] {
	detector := LastDetector[R, L]{bound[R, L](options)}
	register[L](detector)
	return detector
}

func (LastDetector[R, L]) RunsLast() {}

// TracedRule can describe the value flow behind a finding.
type TracedRule interface {
	Rule
	ChainPath(engine.Match, *engine.Codebase) []string
}

// TracedDetector attaches a rule's value-flow evidence to its findings.
type TracedDetector[R TracedRule, L Language] struct {
	Detector[R, L]
}

func BindTraced[R TracedRule, L Language](options ...Option) TracedDetector[R, L] {
	detector := TracedDetector[R, L]{bound[R, L](options)}
	register[L](detector)
	return detector
}

func (TracedDetector[R, L]) ChainPath(finding engine.Match, codebase *engine.Codebase) []string {
	var rule R
	return rule.ChainPath(finding, codebase)
}

// GroupedRule is a shared rule whose findings recur in groups: the key says which group a finding is in, the same in every language.
type GroupedRule interface {
	Rule
	GroupKey(match engine.Match) (string, bool)
}

// GroupedDetector judges one language by a grouped shared rule, so a report names each finding's twins.
type GroupedDetector[R GroupedRule, L Language] struct {
	Detector[R, L]
}

func BindGrouped[R GroupedRule, L Language](options ...Option) GroupedDetector[R, L] {
	detector := GroupedDetector[R, L]{bound[R, L](options)}
	register[L](detector)

	return detector
}

func (GroupedDetector[R, L]) GroupKey(match engine.Match) (string, bool) {
	var rule R

	return rule.GroupKey(match)
}

func bound[R Rule, L Language](options []Option) Detector[R, L] {
	var settings binding
	for _, option := range options {
		option(&settings)
	}
	if _, already := registered.LoadOrStore(reflect.TypeFor[R](), true); !already {
		sins.Register(catalog.Shared, Sin[R]{})
	}

	return Detector[R, L]{repentable: settings.repentable, unpublished: settings.unpublished, excused: settings.excused, exemptions: settings.exemptions, reject: settings.reject}
}

func register[L Language](detector detectors.Detector) {
	var language L
	detectors.Register(language.Engine(), detector)
}

func (Detector[R, L]) Sin() sins.Sin {
	return Sin[R]{}
}

func (d Detector[R, L]) Find(codebase *engine.Codebase) []engine.Match {
	var rule R
	var language L
	part := codebase.Of(catalog.LanguagesOf(language.Engine())...)
	if candidates, ok := any(rule).(CandidateRule); ok && d.reject != nil {
		return candidates.FindRejecting(part, d.reject)
	}
	found := rule.Find(part)
	if d.excused == nil {
		return found
	}

	return slices.DeleteFunc(found, func(finding engine.Match) bool { return d.excused(codebase, finding) })
}

func (d Detector[R, L]) Exemptions() []packages.Exemption {
	return d.exemptions
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

func (Detector[R, L]) Engine() catalog.Engine {
	var language L
	return language.Engine()
}

func (d Detector[R, L]) Repents() bool {
	return d.repentable
}

func (d Detector[R, L]) Published() bool {
	var rule R

	return !d.unpublished && !catalog.IsUnpublished(rule)
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
