// Package rule is a detector written as data, in the commandments:neutral-rule/v2 format: the engines it judges, the
// sin it finds, the definitions it names, and the query over the generic tree that finds it. The same reading serves
// the rules the tool ships and the rules a project writes.
package rule

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"maps"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
	"github.com/jessegall/code-commandments/skill"
)

// Format is the one rule format the tool reads.
const Format = "commandments:neutral-rule/v2"

var languages = map[catalog.Engine][]contract.Language{
	catalog.PHP:        {contract.PHP},
	catalog.Vue:        {contract.Vue},
	catalog.TypeScript: {contract.TypeScript, contract.Vue},
	catalog.Python:     {contract.Python},
	catalog.CSharp:     {contract.CSharp},
	catalog.Go:         {contract.Go},
}

var layered = map[catalog.Engine]layerSpelling{
	catalog.PHP:    {`\`, false},
	catalog.Python: {".", true},
	catalog.CSharp: {".", true},
	catalog.Go:     {"/", true},
}

// layerSpelling is how one engine spells a namespace: the separator between its parts, and whether its case matters.
type layerSpelling struct {
	separator     string
	caseSensitive bool
}

// file is a rule as it is written.
type file struct {
	Schema      string                `json:"$schema"`
	Engine      engines               `json:"engine"`
	Sin         sinFile               `json:"sin"`
	Parameters  map[string]parameter  `json:"parameters,omitempty"`
	Definitions map[string]definition `json:"definitions,omitempty"`
	Find        find                  `json:"find"`
	Judges      []string              `json:"judges,omitempty"`
	Tags        []string              `json:"tags,omitempty"`
}

// sinFile is a rule's metadata block: the sin it names and every name it answers to, the skill that teaches its fix,
// what it says, the package it needs, the helpers its fix may scaffold, how its findings are reported, and whether
// it is shipped yet.
type sinFile struct {
	Name        string       `json:"name"`
	Aliases     []string     `json:"aliases,omitempty"`
	Skill       string       `json:"skill"`
	Description string       `json:"description"`
	Rule        string       `json:"rule,omitempty"`
	Suggestion  string       `json:"suggestion,omitempty"`
	Requires    *requirement `json:"requires,omitempty"`
	Scaffolds   []scaffold   `json:"scaffolds,omitempty"`
	Reporting   reporting    `json:"reporting,omitempty"`
	Unpublished bool         `json:"unpublished,omitempty"`
}

// requirement is the package a project must hold for the rule to judge it.
type requirement struct {
	Name      string `json:"name"`
	Ecosystem string `json:"ecosystem"`
}

// scaffold is a helper the fix uses that a project may not have yet: where it goes and what it starts as.
type scaffold struct {
	Path     string `json:"path"`
	Stub     string `json:"stub"`
	Frontend bool   `json:"frontend,omitempty"`
}

// reporting is how a rule's findings are read and said: whether its answer for a file rests on other files, whether
// it runs after every other rule, whether it asks for the best design, and whether a finding says the change it
// advises.
type reporting struct {
	CrossFile          bool `json:"crossFile,omitempty"`
	RunsLast           bool `json:"runsLast,omitempty"`
	RequiresBestDesign bool `json:"requiresBestDesign,omitempty"`
	Advice             bool `json:"advice,omitempty"`
}

// parameter is a value a rule reads as $param.<name>, which a project sets under configure: its type (number, string,
// list or map), the value it has when nothing sets it, and what it means.
type parameter struct {
	Type        string `json:"type"`
	Default     any    `json:"default"`
	Description string `json:"description"`
}

// parameterTypes is what each parameter type holds, as JSON decodes it.
var parameterTypes = map[string]func(any) bool{
	"number": func(v any) bool { _, is := v.(float64); return is },
	"string": func(v any) bool { _, is := v.(string); return is },
	"list":   func(v any) bool { _, is := v.([]any); return is },
	"map":    func(v any) bool { _, is := v.(map[string]any); return is },
}

// definition is a named expression a rule calls as it calls an operator, its arguments bound inside it alone.
type definition struct {
	Args []string `json:"args"`
	Expr Expr     `json:"expr"`
}

// find is the query: select the nodes to judge and bind each to as, keep those where holds, bind let's names in order,
// keep those having holds for, and emit the node each finding reports at. A find reading the rows of a relation names
// from, the pipeline they go through and the relations it builds instead.
type find struct {
	Select    selection           `json:"select,omitempty"`
	As        string              `json:"as,omitempty"`
	Where     *Expr               `json:"where,omitempty"`
	Let       []binding           `json:"let,omitempty"`
	Having    *Expr               `json:"having,omitempty"`
	Emit      *Expr               `json:"emit,omitempty"`
	Advice    *Expr               `json:"advice,omitempty"`
	From      source              `json:"from,omitempty"`
	Pipeline  []stage             `json:"pipeline,omitempty"`
	Relations map[string]relation `json:"relations,omitempty"`
}

// selection is the kinds a find selects, written as one name or as a list of them.
type selection []string

func (s *selection) UnmarshalJSON(text []byte) error {
	var one string
	if json.Unmarshal(text, &one) == nil {
		*s = selection{one}

		return nil
	}

	var several []string
	if err := json.Unmarshal(text, &several); err != nil {
		return fmt.Errorf("select is one kind's name or a list of them")
	}
	*s = several

	return nil
}

// binding is one of let's ordered pairs: a name and the expression it is bound to, written ["name", expression].
type binding struct {
	Name string
	Expr Expr
}

func (b *binding) UnmarshalJSON(text []byte) error {
	var pair []json.RawMessage
	if err := json.Unmarshal(text, &pair); err != nil || len(pair) != 2 || json.Unmarshal(pair[0], &b.Name) != nil {
		return fmt.Errorf(`each of let's bindings is a pair ["name", expression]`)
	}

	return json.Unmarshal(pair[1], &b.Expr)
}

// Rule is a detector written as data.
type Rule struct {
	name    string
	engines []catalog.Engine
	sin     ownSin
	read    file
	judges  []string
	tags    []string
	written []byte
	layers  string
	origin  string
	set     map[string]any
}

// engines are the engines a rule's own file names, written as one name or as a list of them.
type engines []string

func (e *engines) UnmarshalJSON(text []byte) error {
	var one string
	if json.Unmarshal(text, &one) == nil {
		*e = engines{one}

		return nil
	}

	var several []string
	if err := json.Unmarshal(text, &several); err != nil {
		return fmt.Errorf("engine is one engine's name or a list of them")
	}

	*e = several

	return nil
}

// Skills finds the skill a rule's sin names: the project's own, else a shipped one.
type Skills func(slug string) (skill.Skill, bool)

// OldFormat is a rule file written before commandments:neutral-rule/v2: the step it is first written with, when it has
// one. A loader that knows the file's path names it in File.
type OldFormat struct {
	File string
	Step string
}

func (e *OldFormat) Error() string {
	if e.Step == "" {
		return fmt.Sprintf("%s is written in the old rule format. Rewrite this rule using %s.", e.File, Format)
	}

	return fmt.Sprintf("%s uses the old %s step. Rewrite this rule using %s.", e.File, e.Step, Format)
}

// Parse reads one rule file alone and checks all of it before any of it runs. A rule calling another file's
// definitions is read through Load, which knows the other files.
func Parse(name string, text []byte, skills Skills) (Rule, error) {
	return parse(name, text, skills, nil, nil)
}

// outsideDefinitions finds the definitions a call of another file's definition brings in, by the name it is called
// with; nothing when the call names no other file.
type outsideDefinitions func(call string) (map[string]definition, error)

func parse(name string, text []byte, skills Skills, outside outsideDefinitions, shared map[string]relation) (Rule, error) {
	if err := formatOf(name, text); err != nil {
		return Rule{}, err
	}

	var read file
	if err := strictly(text, &read); err != nil {
		return Rule{}, fmt.Errorf("%s: %w", name, err)
	}

	if err := read.bringIn(outside); err != nil {
		return Rule{}, fmt.Errorf("%s: %w", name, err)
	}
	read.bringRelations(shared)
	// A shared relation may call another file's definitions too, so they are brought in again once it is here.
	if err := read.bringIn(outside); err != nil {
		return Rule{}, fmt.Errorf("%s: %w", name, err)
	}

	judges, err := enginesOf(read.Engine)
	if err != nil {
		return Rule{}, fmt.Errorf("%s: %w", name, err)
	}

	if read.Sin.Name == "" || read.Sin.Skill == "" {
		return Rule{}, fmt.Errorf("%s: a sin needs a name and the skill that teaches its fix", name)
	}

	taught, found := skills(read.Sin.Skill)
	if !found {
		return Rule{}, fmt.Errorf("%s: no skill %q, among the project's own or the shipped ones", name, read.Sin.Skill)
	}

	if err := validate(read); err != nil {
		return Rule{}, fmt.Errorf("%s: %w", name, err)
	}

	return Rule{name, judges, read.Sin.sin(taught), read, folders(read.Judges), read.Tags, text, "", "", nil}, nil
}

// WithParameter is the rule with a declared parameter set to a value, as configure sets it: an undeclared parameter
// or a value of another type is refused by name.
func (r Rule) WithParameter(name string, value any) (Rule, error) {
	declared, found := r.read.Parameters[name]
	if !found {
		return r, fmt.Errorf("the rule %s declares no parameter %q%s", r.name, name, didYouMean(name, slices.Collect(maps.Keys(r.read.Parameters))))
	}
	if whole, isWhole := value.(int); isWhole {
		value = float64(whole)
	}
	if !parameterTypes[declared.Type](value) {
		return r, fmt.Errorf("the parameter %q of the rule %s is a %s, and %v is not", name, r.name, declared.Type, value)
	}
	set := maps.Clone(r.set)
	if set == nil {
		set = map[string]any{}
	}
	set[name] = value
	r.set = set

	return r, nil
}

// parameters is each declared parameter's value: the one configure set, else its default.
func (r Rule) parameters() value {
	values := record{}
	for name, declared := range r.read.Parameters {
		values[name] = fromJSON(declared.Default)
		if set, isSet := r.set[name]; isSet {
			values[name] = fromJSON(set)
		}
	}

	return known(values)
}

// fromJSON is a value as JSON decoded it: a list item by item, a map field by field.
func fromJSON(decoded any) value {
	switch held := decoded.(type) {
	case []any:
		items := make([]value, len(held))
		for at, item := range held {
			items[at] = fromJSON(item)
		}

		return known(items)
	case map[string]any:
		fields := record{}
		for name, field := range held {
			fields[name] = fromJSON(field)
		}

		return known(fields)
	}

	return known(decoded)
}

// strictly decodes a rule file, refusing a key nothing reads and anything after the one object.
func strictly(text []byte, read *file) error {
	decoder := json.NewDecoder(bytes.NewReader(text))
	decoder.DisallowUnknownFields()
	if err := decoder.Decode(read); err != nil {
		if key, unknown := unknownKey(err); unknown {
			return fmt.Errorf("nothing reads a key called %q%s", key, didYouMean(key, knownKeys))
		}

		return err
	}
	if decoder.More() {
		return errors.New("the rule is one JSON object, and more follows it")
	}

	return nil
}

// bringIn adds every definition of another file the rule calls to its own, under the name it calls it by.
func (f *file) bringIn(outside outsideDefinitions) error {
	if outside == nil {
		return nil
	}
	var failed error
	for _, expression := range f.expressions() {
		expression.Each(func(each Expr) {
			_, isOperator := operators[each.Operator]
			_, isOwn := f.Definitions[each.Operator]
			if failed != nil || !each.IsCall() || isOperator || isOwn {
				return
			}
			brought, err := outside(each.Operator)
			if err != nil {
				failed = err

				return
			}
			if f.Definitions == nil && brought != nil {
				f.Definitions = map[string]definition{}
			}
			maps.Copy(f.Definitions, brought)
		})
	}

	return failed
}

// FormerNames are the other names the rule answers to: its sin's name and every alias it declares.
func (r Rule) FormerNames() []string {
	return append([]string{r.sin.definition.Name}, r.sin.definition.Formerly...)
}

// bringRelations adds every shared relation the find reads, and the relations those read, to the find's own.
func (f *file) bringRelations(shared map[string]relation) {
	var wanted []string
	want := func(names ...string) {
		for _, name := range names {
			if _, own := f.Find.Relations[name]; !own {
				if _, isShared := shared[name]; isShared && !slices.Contains(wanted, name) {
					wanted = append(wanted, name)
				}
			}
		}
	}
	want(f.Find.From...)
	for _, step := range f.Find.Pipeline {
		want(step.Join)
	}
	want(f.rowsRead()...)
	for _, defined := range f.Find.Relations {
		want(defined.From...)
		for _, step := range defined.Pipeline {
			want(step.Join)
		}
	}
	for len(wanted) > 0 {
		name := wanted[0]
		wanted = wanted[1:]
		if f.Find.Relations == nil {
			f.Find.Relations = map[string]relation{}
		}
		f.Find.Relations[name] = shared[name]
		if f.Definitions == nil && len(shared[name].definitions) > 0 {
			f.Definitions = map[string]definition{}
		}
		maps.Copy(f.Definitions, shared[name].definitions)
		want(shared[name].From...)
		for _, step := range shared[name].Pipeline {
			want(step.Join)
		}
	}
}

// rowsRead is every relation an expression of the rule names to read its rows: rowsOf, hasRow, countOf and rowOf.
func (f file) rowsRead() []string {
	var names []string
	note := func(expression Expr) Expr {
		expression.Each(func(each Expr) {
			if slices.Contains([]string{"rowsOf", "hasRow", "countOf", "rowOf"}, each.Operator) && len(each.Operands) > 0 {
				if name, isText := each.Operands[0].Value.(string); isText && !slices.Contains(names, name) {
					names = append(names, name)
				}
			}
		})

		return expression
	}
	for _, written := range []*Expr{f.Find.Where, f.Find.Having, f.Find.Emit, f.Find.Advice} {
		if written != nil {
			note(*written)
		}
	}
	for _, each := range f.Find.Let {
		note(each.Expr)
	}
	for _, step := range f.Find.Pipeline {
		step.mapped(note)
	}
	for _, defined := range f.Definitions {
		note(defined.Expr)
	}

	return names
}

// Origin is where the rule comes from, installed or the project's, when a loader read it.
func (r Rule) Origin() string {
	return r.origin
}

// knownKeys is every key a rule file may write, for the nearest one to a misspelt key.
var knownKeys = []string{
	"$schema", "engine", "sin", "parameters", "type", "default", "definitions", "find", "requires", "ecosystem",
	"scaffolds", "path", "stub", "frontend", "reporting", "crossFile", "runsLast", "requiresBestDesign", "advice",
	"unpublished", "judges", "tags", "name", "aliases", "skill", "description",
	"rule", "suggestion", "args", "expr", "select", "as", "where", "let", "having", "emit", "from", "pipeline", "relations",
}

// formatOf refuses a file in any format but commandments:neutral-rule/v2, naming the old step it is written with.
func formatOf(name string, text []byte) error {
	var written struct {
		Schema string `json:"$schema"`
		Find   struct {
			Where  json.RawMessage `json:"where"`
			Reject json.RawMessage `json:"reject"`
		} `json:"find"`
	}
	if err := json.Unmarshal(text, &written); err != nil {
		return fmt.Errorf("%s: %w", name, err)
	}

	switch {
	case written.Schema == Format:
		return nil
	case strings.HasPrefix(written.Schema, "commandments:neutral-rule/"):
		return fmt.Errorf("%s: the rule names the format %s, and this tool reads %s", name, written.Schema, Format)
	}

	old := &OldFormat{File: name + ".json"}
	for _, steps := range []json.RawMessage{written.Find.Where, written.Find.Reject} {
		if step := firstStep(steps); step != "" {
			old.Step = step

			break
		}
	}

	return old
}

// firstStep is the first key of the first step of an old where or reject list, such as redundantElse.
func firstStep(steps json.RawMessage) string {
	var list []json.RawMessage
	if json.Unmarshal(steps, &list) != nil || len(list) == 0 {
		return ""
	}

	decoder := json.NewDecoder(bytes.NewReader(list[0]))
	if opened, err := decoder.Token(); err != nil || opened != json.Delim('{') {
		return ""
	}
	key, err := decoder.Token()
	if err != nil {
		return ""
	}
	named, _ := key.(string)

	return named
}

func enginesOf(named engines) ([]catalog.Engine, error) {
	if len(named) == 0 {
		return nil, errors.New("a rule names the engine it judges, or the engines")
	}

	var judges []catalog.Engine
	for _, each := range named {
		engine, _ := catalog.EngineNamed(each)
		if _, known := languages[engine]; !known {
			return nil, errors.New(noneOf("engine", each, engineNames(), append(engineNames(), catalog.FormerNames()...)))
		}

		if slices.Contains(judges, engine) {
			return nil, fmt.Errorf("engine %q is named twice", each)
		}

		judges = append(judges, engine)
	}

	return judges, nil
}

func folders(named []string) []string {
	var kept []string
	for _, folder := range named {
		if trimmed := strings.Trim(strings.TrimSpace(folder), "/"); trimmed != "" {
			kept = append(kept, trimmed)
		}
	}

	return kept
}

func (r Rule) TakesPublicContracts() bool {
	_, takes := r.read.Parameters["publicContracts"]

	return takes
}

// WithPublicContracts is the rule with the types a project publishes as contracts, set into its publicContracts
// parameter when it declares one.
func (r Rule) WithPublicContracts(symbols []string) Rule {
	if r.TakesPublicContracts() && len(symbols) > 0 {
		listed := make([]any, len(symbols))
		for at, each := range symbols {
			listed[at] = each
		}
		r, _ = r.WithParameter("publicContracts", listed)
	}

	return r
}

// WithLayers is the rule judging against the layers a project declares for its engine.
func (r Rule) WithLayers(declared map[string][]string) Rule {
	if _, declares := firstLayered(r.engines); declares {
		r.layers = fmt.Sprint(declared)
	}
	if _, takes := r.read.Parameters["layers"]; takes && len(declared) > 0 {
		layers := map[string]any{}
		for name, mayUse := range declared {
			allowed := make([]any, len(mayUse))
			for at, each := range mayUse {
				allowed[at] = each
			}
			layers[name] = allowed
		}
		r, _ = r.WithParameter("layers", layers)
	}

	return r
}

func (r Rule) Name() string {
	return r.name
}

func (r Rule) Engine() catalog.Engine {
	if len(r.engines) == 0 {
		return ""
	}

	return r.engines[0]
}

func (r Rule) Engines() []catalog.Engine {
	return r.engines
}

// spellingOf is how the engine a language is judged under spells its namespaces, where it declares layers.
func spellingOf(language contract.Language) (layerSpelling, bool) {
	for engine, judged := range languages {
		if spelling, declares := layered[engine]; declares && slices.Contains(judged, language) {
			return spelling, true
		}
	}

	return layerSpelling{}, false
}

func firstLayered(engines []catalog.Engine) (layerSpelling, bool) {
	for _, engine := range engines {
		if spelling, declares := layered[engine]; declares {
			return spelling, true
		}
	}

	return layerSpelling{}, false
}

func judgedLanguages(engines []catalog.Engine) []contract.Language {
	var judged []contract.Language
	for _, engine := range engines {
		for _, language := range languages[engine] {
			if !slices.Contains(judged, language) {
				judged = append(judged, language)
			}
		}
	}

	return judged
}

func (r Rule) Sin() sins.Sin {
	return r.sin
}

func (r Rule) Tags() []string {
	return r.tags
}

func (r Rule) Judges() []string {
	return r.judges
}

// Find is every node the rule finds in the codebase, in the languages its engines judge.
func (r Rule) Find(codebase *engine.Codebase) []engine.Match {
	return r.evaluate(codebase.Of(judgedLanguages(r.engines)...))
}

func noneOf(key, value string, allowed []string, meant ...[]string) string {
	nearest := allowed
	if len(meant) > 0 {
		nearest = meant[0]
	}

	return fmt.Sprintf("%s %q is none of %s%s", key, value, strings.Join(allowed, ", "), didYouMean(value, nearest))
}

func engineNames() []string {
	var names []string
	for _, engine := range catalog.Engines {
		names = append(names, string(engine))
	}

	return names
}

// ownSin is a sin a rule declares.
type ownSin struct {
	definition sins.Definition
	scaffolds  []scaffold
}

func (s ownSin) Definition() sins.Definition {
	return s.definition
}

// Scaffolds are the helpers the fix may scaffold into a project.
func (s ownSin) Scaffolds() []sins.Scaffold {
	var made []sins.Scaffold
	for _, each := range s.scaffolds {
		made = append(made, sins.Scaffold{Path: each.Path, Stub: each.Stub, Frontend: each.Frontend})
	}

	return made
}

// sin is the sin the metadata block names, taught by the skill given.
func (s sinFile) sin(taught skill.Skill) ownSin {
	return ownSin{sins.Definition{
		Name:        s.Name,
		Skill:       taught,
		Description: s.Description,
		Rule:        s.Rule,
		Suggestion:  s.Suggestion,
		Formerly:    s.Aliases,
		Requires:    s.required(),
	}, s.Scaffolds}
}

// Name is the sin's canonical name, which a config, a marker and a finding name it by.
func (s ownSin) Name() string {
	return s.definition.Name
}

// FormerNames are the other names the sin answers to.
func (s ownSin) FormerNames() []string {
	return s.definition.Formerly
}

func (s sinFile) required() sins.Package {
	if s.Requires == nil {
		return sins.Package{}
	}

	return sins.Package{Name: s.Requires.Name, Ecosystem: sins.Ecosystem(s.Requires.Ecosystem)}
}

// IsStub says whether the rule file holds only its metadata, its find still to be written: a stub is not shipped.
func (f file) IsStub() bool {
	return f.Find.Select == nil && f.Find.From == nil
}

func (r Rule) RequiresBestDesign() bool {
	return r.read.Sin.Reporting.RequiresBestDesign
}

// IsUnpublished says whether the rule is still being calibrated, so no catalogue ships it.
func (r Rule) IsUnpublished() bool {
	return r.read.Sin.Unpublished
}
