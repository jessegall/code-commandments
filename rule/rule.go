// Package rule is a detector a project writes as data: the engine it judges, the sin it finds, and the query that finds it, composed from the same selectors and checks a shipped detector composes in code.
package rule

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"maps"
	"path"
	"path/filepath"
	"regexp"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/prose"
	"github.com/jessegall/code-commandments/sins"
	"github.com/jessegall/code-commandments/skill"
)

var languages = map[catalog.Engine][]contract.Language{
	catalog.PHP:        {contract.PHP},
	catalog.Vue:        {contract.Vue},
	catalog.TypeScript: {contract.TypeScript, contract.Vue},
	catalog.Python:     {contract.Python},
	catalog.CSharp:     {contract.CSharp},
	catalog.Go:         {contract.Go},
}

var cases = map[string]*regexp.Regexp{
	"camel":  regexp.MustCompile(`^[a-z][a-z0-9]*([A-Z][a-z0-9]*)*$`),
	"pascal": regexp.MustCompile(`^[A-Z][A-Za-z0-9]*[a-z][A-Za-z0-9]*$`),
	"snake":  regexp.MustCompile(`^[a-z][a-z0-9]*(_[a-z0-9]+)*$`),
	"upper":  regexp.MustCompile(`^[A-Z][A-Z0-9]*(_[A-Z0-9]+)*$`),
	"kebab":  regexp.MustCompile(`^[a-z][a-z0-9]*(-[a-z0-9]+)*$`),
}

// file is a rule as it is written.
type file struct {
	Schema string   `json:"$schema,omitempty"`
	Engine engines  `json:"engine"`
	Sin    sinFile  `json:"sin"`
	Find   find     `json:"find"`
	Fix    *Fix     `json:"fix,omitempty"`
	Judges []string `json:"judges,omitempty"`
}

type sinFile struct {
	Name        string `json:"name"`
	Description string `json:"description"`
	Rule        string `json:"rule,omitempty"`
	Suggestion  string `json:"suggestion,omitempty"`
	Skill       string `json:"skill"`
}

type find struct {
	Select string `json:"select,omitempty"`
	From   string `json:"from,omitempty"`
	Where  []Step `json:"where,omitempty"`
	Reject []Step `json:"reject,omitempty"`

	inner *Rule
}

// Step is one check of a rule's query, on the node itself or, with Of or a nested step, on one related to it.
type Step struct {
	Is                string          `json:"is,omitempty"`
	Kind              string          `json:"kind,omitempty"`
	Name              string          `json:"name,omitempty"`
	NameIn            []string        `json:"nameIn,omitempty"`
	NameLike          string          `json:"nameLike,omitempty"`
	NameMatches       string          `json:"nameMatches,omitempty"`
	NameCase          string          `json:"nameCase,omitempty"`
	NameMood          string          `json:"nameMood,omitempty"`
	Text              *string         `json:"text,omitempty"`
	TextLike          string          `json:"textLike,omitempty"`
	TextMatches       string          `json:"textMatches,omitempty"`
	Resolves          string          `json:"resolves,omitempty"`
	ResolvesLike      string          `json:"resolvesLike,omitempty"`
	NamespaceLike     string          `json:"namespaceLike,omitempty"`
	Layer             string          `json:"layer,omitempty"`
	HasModifier       string          `json:"hasModifier,omitempty"`
	HasFlag           string          `json:"hasFlag,omitempty"`
	Type              string          `json:"type,omitempty"`
	TypeLike          string          `json:"typeLike,omitempty"`
	Operator          string          `json:"operator,omitempty"`
	OperatorIn        []string        `json:"operatorIn,omitempty"`
	WithinLoop        *bool           `json:"withinLoop,omitempty"`
	Documented        *bool           `json:"documented,omitempty"`
	File              string          `json:"file,omitempty"`
	FileMatches       string          `json:"fileMatches,omitempty"`
	Position          string          `json:"position,omitempty"`
	Field             string          `json:"field,omitempty"`
	Role              string          `json:"role,omitempty"`
	TopLevel          *bool           `json:"topLevel,omitempty"`
	Of                string          `json:"of,omitempty"`
	Descendant        *Descended      `json:"descendant,omitempty"`
	Child             *Childed        `json:"child,omitempty"`
	Body              *Step           `json:"body,omitempty"`
	Inside            *Step           `json:"inside,omitempty"`
	Documents         *Step           `json:"documents,omitempty"`
	Next              *Step           `json:"next,omitempty"`
	Previous          *Step           `json:"previous,omitempty"`
	NestedAtLeast     *Nesting        `json:"nestedAtLeast,omitempty"`
	Counts            *Count          `json:"count,omitempty"`
	Parameters        *Bounds         `json:"parameters,omitempty"`
	Arguments         *Bounds         `json:"arguments,omitempty"`
	Lines             *Bounds         `json:"lines,omitempty"`
	Members           *Tally          `json:"members,omitempty"`
	Complexity        *Bounds         `json:"complexity,omitempty"`
	Extends           string          `json:"extends,omitempty"`
	ExtendsAny        string          `json:"extendsAny,omitempty"`
	Implements        string          `json:"implements,omitempty"`
	Uses              string          `json:"uses,omitempty"`
	HasAttribute      string          `json:"hasAttribute,omitempty"`
	Sibling           string          `json:"sibling,omitempty"`
	IsA               string          `json:"isA,omitempty"`
	All               []Step          `json:"all,omitempty"`
	Any               []Step          `json:"any,omitempty"`
	Not               *Step           `json:"not,omitempty"`
	TypeKind          string          `json:"typeKind,omitempty"`
	HasAnnotation     string          `json:"hasAnnotation,omitempty"`
	ReturnType        string          `json:"returnType,omitempty"`
	ParameterType     string          `json:"parameterType,omitempty"`
	Constructs        string          `json:"constructs,omitempty"`
	Unused            *bool           `json:"unused,omitempty"`
	ReferredFrom      *Step           `json:"referredFrom,omitempty"`
	CalledFrom        string          `json:"calledFrom,omitempty"`
	Calls             *Step           `json:"calls,omitempty"`
	Argument          *Argued         `json:"argument,omitempty"`
	CommentIs         string          `json:"commentIs,omitempty"`
	DocReference      *Referenced     `json:"docReference,omitempty"`
	CommentLike       string          `json:"commentLike,omitempty"`
	CommentMatches    string          `json:"commentMatches,omitempty"`
	DocTag            string          `json:"docTag,omitempty"`
	Duplicated        *Bounds         `json:"duplicated,omitempty"`
	Callers           *Bounds         `json:"callers,omitempty"`
	UnnamedCondition  *bool           `json:"unnamedCondition,omitempty"`
	BindsItsOwnTest   *bool           `json:"bindsItsOwnTest,omitempty"`
	RedundantElse     *bool           `json:"redundantElse,omitempty"`
	SoleLoopBodyGuard *bool           `json:"soleLoopBodyGuard,omitempty"`
	PassesItsElement  *bool           `json:"passesItsElementOn,omitempty"`
	LadderRungs       *Bounds         `json:"ladderRungs,omitempty"`
	Ancestors         *Ancestors      `json:"ancestors,omitempty"`
	Repeated          *Repeats        `json:"repeated,omitempty"`
	DeclaredBy        string          `json:"declaredBy,omitempty"`
	AnsweredBy        *bool           `json:"answeredBy,omitempty"`
	DeclaredElsewhere *bool           `json:"declaredElsewhere,omitempty"`
	SameAs            *Same           `json:"sameAs,omitempty"`
	Value             json.RawMessage `json:"value,omitempty"`
	Nullable          *bool           `json:"nullable,omitempty"`
	TypeShape         string          `json:"typeShape,omitempty"`
	TypeOrigin        string          `json:"typeOrigin,omitempty"`
	ElementType       string          `json:"elementType,omitempty"`
	TestCode          *bool           `json:"testCode,omitempty"`
	Overrides         *bool           `json:"overrides,omitempty"`
	Implicit          *bool           `json:"implicit,omitempty"`
	PHP               string          `json:"php,omitempty"`
	Python            string          `json:"python,omitempty"`
	CSharp            string          `json:"csharp,omitempty"`
	TypeScript        string          `json:"typescript,omitempty"`
	Vue               string          `json:"vue,omitempty"`
	Rule              string          `json:"rule,omitempty"`

	pattern *regexp.Regexp

	layers *engine.LayerStack

	predicate func(engine.Match) bool

	named *Rule
}

func valueHolds(asked json.RawMessage, subject engine.Match) bool {
	if bytes.Equal(asked, []byte("null")) {
		return subject.LiteralKind() == "null"
	}

	held := subject.Value()
	if held == nil {
		return false
	}

	if number, isNumber := asNumber(asked); isNumber {
		text, isText := held.Text()
		was, err := strconv.ParseFloat(text, 64)

		return isText && err == nil && was == number && slices.Contains([]string{"int", "float"}, subject.LiteralKind())
	}

	written, err := held.MarshalJSON()

	return err == nil && bytes.Equal(written, asked)
}

func asNumber(asked json.RawMessage) (float64, bool) {
	var number float64
	if err := json.Unmarshal(asked, &number); err != nil {
		return 0, false
	}

	return number, true
}

var valueScalars = []string{"string", "number", "boolean", "null"}

func (s Step) validValue() error {
	if len(s.Value) == 0 {
		return nil
	}

	var probe any
	if err := json.Unmarshal(s.Value, &probe); err != nil {
		return fmt.Errorf("%s asks for a value that is no JSON: %w", s.describe(), err)
	}

	switch probe.(type) {
	case nil, string, bool, float64:
		return nil
	}

	return fmt.Errorf("a value is a string, a number, a boolean or null, and %s is none of them: %s", s.Value, s.describe())
}

// Nesting is a step counted: the node and the nodes above it that pass it, at least Count of them.
type Nesting struct {
	Step
	Count int `json:"count"`
}

// Bounds are the least and the most a size may be; either may be left out, not both.
type Bounds struct {
	AtLeast *int `json:"atLeast,omitempty"`
	AtMost  *int `json:"atMost,omitempty"`
}

// Count counts the nodes below one that pass a step: its descendants, or its children, those filling one field when Field names it.
type Count struct {
	Descendant *Step  `json:"descendant,omitempty"`
	Child      *Step  `json:"child,omitempty"`
	Field      string `json:"field,omitempty"`
	Distinct   string `json:"distinct,omitempty"`
	Bounds
}

// Same is a check across two nodes: the node the step is about names the same thing as the node reached by following Of from it and then, where Target names one, that target from there.
type Same struct {
	Of     string `json:"of"`
	Target string `json:"target,omitempty"`
}

func (Same) names(subject, other engine.Match) bool {
	if !other.Exists() {
		return false
	}

	if written := subject.Written(); written != "" && written == other.Written() {
		return true
	}

	return subject.SameSyntax(other)
}

func (a Same) other(subject engine.Match) engine.Match {
	reached := (Step{Of: a.Of}).subject(subject)
	if a.Target == "" {
		return reached
	}

	return (Step{Of: a.Target}).subject(reached)
}

// Descended is a step on the nodes below one: every node inside it, or, with Within "ownBody", only the nodes it holds itself, which stop at a function nested in it — so a rule can say "this body does it" rather than "someone does it in here", which is a different claim wherever a closure is handed away.
type Descended struct {
	Step
	Within string `json:"within,omitempty"`
}

const ownBody = "ownBody"

func (d Descended) descendants(match engine.Match) []engine.Match {
	if d.Within == ownBody {
		return match.OwnDescendants()
	}

	return match.Descendants()
}

// Childed is a step on the node's own children, one level down: those filling the field Field names, or every one of them when it names none.
type Childed struct {
	Step
	Field string `json:"field,omitempty"`
}

func (c Childed) children(match engine.Match) []engine.Match {
	if c.Field != "" {
		return match.ChildrenIn(c.Field)
	}

	return match.Children()
}

// Argued is a step on one of a call's arguments: the one At places from the first, or from the last when At is negative, -1 being the last.
type Argued struct {
	Step
	At int `json:"at"`
}

// Tally counts the members of a type that pass its step, or every member when it makes no check; with Inherited, the members its parents and traits declare in the scan too.
type Tally struct {
	Step
	Bounds
	Inherited bool `json:"inherited,omitempty"`
}

var layered = map[catalog.Engine]layerSpelling{
	catalog.PHP:    {`\`, false},
	catalog.Python: {".", true},
	catalog.CSharp: {".", true},
}

// layerSpelling is how one engine spells a namespace: the separator between its parts, and whether its case matters.
type layerSpelling struct {
	separator     string
	caseSensitive bool
}

// Rule is a project's own detector.
type Rule struct {
	name    string
	engines []catalog.Engine
	sin     ownSin
	find    find
	fix     *Fix
	judges  []string
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

func Parse(name string, text []byte, skills Skills) (Rule, error) {
	decoder := json.NewDecoder(bytes.NewReader(text))
	decoder.DisallowUnknownFields()

	var read file
	if err := decoder.Decode(&read); err != nil {
		if key, unknown := unknownKey(err); unknown {
			if where := belongsIn(key); where != "" {
				return Rule{}, fmt.Errorf("%s: nothing reads %q where it stands%s", name, key, where)
			}

			return Rule{}, fmt.Errorf("%s: no check or key is called %q%s", name, key, didYouMean(key, knownKeys()))
		}

		return Rule{}, fmt.Errorf("%s: %w", name, err)
	}

	if decoder.More() {
		return Rule{}, fmt.Errorf("%s: the rule is one JSON object, and more follows it", name)
	}

	if len(read.Engine) == 0 {
		return Rule{}, fmt.Errorf("%s: a rule names the engine it judges, or the engines", name)
	}

	var judges []catalog.Engine
	for _, named := range read.Engine {
		engine, _ := catalog.EngineNamed(named)
		if _, known := languages[engine]; !known {
			return Rule{}, fmt.Errorf("%s: %s", name, noneOf("engine", named, engineNames(), append(engineNames(), catalog.FormerNames()...)))
		}

		if slices.Contains(judges, engine) {
			return Rule{}, fmt.Errorf("%s: engine %q is named twice", name, named)
		}

		judges = append(judges, engine)
	}

	judged := judgedLanguages(judges)

	if read.Sin.Name == "" || read.Sin.Skill == "" {
		return Rule{}, fmt.Errorf("%s: a sin needs a name and the skill that teaches its fix", name)
	}

	taught, found := skills(read.Sin.Skill)
	if !found {
		return Rule{}, fmt.Errorf("%s: no skill %q, among the project's own or the shipped ones", name, read.Sin.Skill)
	}

	if (read.Find.Select == "") == (read.Find.From == "") {
		return Rule{}, fmt.Errorf("%s: a rule finds what it selects or what another rule found, naming one of select and from", name)
	}

	if read.Find.Select != "" {
		if _, err := selector(read.Find.Select); err != nil {
			return Rule{}, fmt.Errorf("%s: %w", name, err)
		}

		if err := selected(read.Find.Select, judged); err != nil {
			return Rule{}, fmt.Errorf("%s: %w", name, err)
		}
	}

	for _, steps := range [][]Step{read.Find.Where, read.Find.Reject} {
		for i := range steps {
			if err := steps[i].prepare(); err != nil {
				return Rule{}, fmt.Errorf("%s: %w", name, err)
			}

			if err := steps[i].ownChecks(judged); err != nil {
				return Rule{}, fmt.Errorf("%s: %w", name, err)
			}

			if err := steps[i].declaredValues(judged); err != nil {
				return Rule{}, fmt.Errorf("%s: %w", name, err)
			}

			for _, engine := range judges {
				if _, declares := layered[engine]; !declares && steps[i].usesLayers() {
					return Rule{}, fmt.Errorf("%s: a layer step needs declared layers, and the %s engine has none", name, engine)
				}
			}
		}
	}

	if read.Fix != nil {
		if err := read.Fix.prepare(); err != nil {
			return Rule{}, fmt.Errorf("%s: %w", name, err)
		}
	}

	return Rule{name, judges, ownSin{sins.Definition{
		Name:        read.Sin.Name,
		Skill:       taught,
		Description: read.Sin.Description,
		Rule:        read.Sin.Rule,
		Suggestion:  read.Sin.Suggestion,
	}}, read.Find, read.Fix, folders(read.Judges)}, nil
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

func (r Rule) WithLayers(declared map[string][]string) Rule {
	spelling, declares := firstLayered(r.engines)
	if !declares {
		return r
	}

	stack := engine.Layers(declared, spelling.separator, spelling.caseSensitive)

	return r.withLayers(&stack)
}

func (r Rule) withLayers(stack *engine.LayerStack) Rule {
	r.find.Where = withLayers(r.find.Where, stack)
	r.find.Reject = withLayers(r.find.Reject, stack)

	if r.find.inner != nil {
		inner := r.find.inner.withLayers(stack)
		r.find.inner = &inner
	}

	return r
}

func withLayers(steps []Step, stack *engine.LayerStack) []Step {
	copied := make([]Step, len(steps))

	for i, step := range steps {
		copied[i] = step.withLayers(stack)
	}

	return copied
}

func layersInto(stack *engine.LayerStack, steps ...**Step) {
	for _, nested := range steps {
		if *nested == nil {
			continue
		}
		copied := (*nested).withLayers(stack)
		*nested = &copied
	}
}

func (s Step) withLayers(stack *engine.LayerStack) Step {
	s.layers = stack

	layersInto(stack, &s.Inside, &s.Next, &s.Previous, &s.Calls)

	if s.Descendant != nil {
		descended := *s.Descendant
		descended.Step = descended.Step.withLayers(stack)
		s.Descendant = &descended
	}

	if s.NestedAtLeast != nil {
		counted := *s.NestedAtLeast
		counted.Step = counted.Step.withLayers(stack)
		s.NestedAtLeast = &counted
	}

	if s.Counts != nil {
		counted := *s.Counts
		layersInto(stack, &counted.Descendant, &counted.Child)
		s.Counts = &counted
	}

	if s.Members != nil {
		tally := *s.Members
		tally.Step = tally.Step.withLayers(stack)
		s.Members = &tally
	}

	if s.Argument != nil {
		argued := *s.Argument
		argued.Step = argued.Step.withLayers(stack)
		s.Argument = &argued
	}

	if s.Child != nil {
		childed := *s.Child
		childed.Step = childed.Step.withLayers(stack)
		s.Child = &childed
	}

	if s.Body != nil {
		copied := s.Body.withLayers(stack)
		s.Body = &copied
	}

	for _, listed := range []*[]Step{&s.All, &s.Any} {
		if *listed != nil {
			*listed = withLayers(*listed, stack)
		}
	}

	if s.Not != nil {
		copied := s.Not.withLayers(stack)
		s.Not = &copied
	}

	if s.named != nil {
		named := s.named.withLayers(stack)
		s.named = &named
	}

	return s
}

func (s *Step) nested() []*Step {
	var nested []*Step

	for _, step := range []*Step{s.Inside, s.Documents, s.Next, s.Previous, s.Calls, s.ReferredFrom} {
		if step != nil {
			nested = append(nested, step)
		}
	}

	if s.NestedAtLeast != nil {
		nested = append(nested, &s.NestedAtLeast.Step)
	}

	if s.Counts != nil {
		for _, step := range []*Step{s.Counts.Descendant, s.Counts.Child} {
			if step != nil {
				nested = append(nested, step)
			}
		}
	}

	if s.Members != nil && s.Members.Step.checks() > 0 {
		nested = append(nested, &s.Members.Step)
	}

	if s.Argument != nil {
		nested = append(nested, &s.Argument.Step)
	}

	if s.Child != nil {
		nested = append(nested, &s.Child.Step)
	}

	if s.Body != nil {
		nested = append(nested, s.Body)
	}

	if s.Descendant != nil {
		nested = append(nested, &s.Descendant.Step)
	}

	for i := range s.All {
		nested = append(nested, &s.All[i])
	}

	for i := range s.Any {
		nested = append(nested, &s.Any[i])
	}

	if s.Not != nil {
		nested = append(nested, s.Not)
	}

	return nested
}

func (s Step) usesLayers() bool {
	return s.Layer != "" || slices.ContainsFunc(s.nested(), (*Step).usesLayers)
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

func engineList(engines []catalog.Engine) string {
	var names []string
	for _, engine := range engines {
		names = append(names, string(engine))
	}

	if len(names) == 1 {
		return "the " + names[0] + " engine"
	}

	return "the " + strings.Join(names, " and ") + " engines"
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

func (r Rule) Judges() []string {
	return r.judges
}

func (r Rule) Find(codebase *engine.Codebase) []engine.Match {
	if r.find.inner != nil {
		return narrowed(r.find.inner.Find(codebase), r.find)
	}

	selected, _ := selector(r.find.Select)
	query := selected(codebase.Of(judgedLanguages(r.engines)...))

	for _, step := range r.find.Where {
		query = query.Where(step.check)
	}

	for _, step := range r.find.Reject {
		query = query.Reject(step.check)
	}

	return query.Get()
}

func narrowed(found []engine.Match, by find) []engine.Match {
	var kept []engine.Match
	for _, match := range found {
		if steps(by.Where).hold(match) && !steps(by.Reject).any(match) {
			kept = append(kept, match)
		}
	}

	return kept
}

func (r Rule) Satisfies(match engine.Match) bool {
	if r.find.inner != nil && !r.find.inner.Satisfies(match) {
		return false
	}

	if r.find.inner == nil && !admits(r.find.Select, match) {
		return false
	}

	return steps(r.find.Where).hold(match) && !steps(r.find.Reject).any(match)
}

// steps is a run of a rule's steps, asked about one node.
type steps []Step

func (s steps) hold(match engine.Match) bool {
	return !slices.ContainsFunc(s, func(step Step) bool { return !step.check(match) })
}

func (s steps) any(match engine.Match) bool {
	return slices.ContainsFunc(s, func(step Step) bool { return step.check(match) })
}

func admits(named string, match engine.Match) bool {
	if kind, own := strings.CutPrefix(named, "kind:"); own {
		return match.Kind() == kind
	}

	if spelling, construct := strings.CutPrefix(named, constructSelector); construct {
		return namesTheKind(spelling, match)
	}

	if named == commentSelector {
		return match.Kind() == engine.CommentKind
	}

	return match.Is(engine.Neutral(named))
}

func namesTheKind(word string, subject engine.Match) bool {
	if subject.Source() == nil {
		return false
	}

	vocabulary, held := VocabularyOf(subject.Source().Language())
	if !held {
		return false
	}

	construct, spelled := vocabulary.Spelled(word)

	return spelled && construct.Kind == subject.Kind()
}

func selector(named string) (func(*engine.Codebase) *engine.Query, error) {
	if kind, own := strings.CutPrefix(named, "kind:"); own && kind != "" {
		return func(c *engine.Codebase) *engine.Query { return c.WhereKind(kind) }, nil
	}

	if named == commentSelector {
		return func(c *engine.Codebase) *engine.Query { return c.WhereComment() }, nil
	}

	if spelling, construct := strings.CutPrefix(named, constructSelector); construct && spelling != "" {
		return func(c *engine.Codebase) *engine.Query {
			return c.Where(func(match engine.Match) bool { return namesTheKind(spelling, match) })
		}, nil
	}

	if !slices.Contains(engine.Neutrals, engine.Neutral(named)) {
		return nil, fmt.Errorf("select %q is neither a neutral kind (%s), nor %s, nor construct:<spelling>, nor kind:<Kind>%s", named, neutralList(), commentSelector, didYouMean(named, append(neutralNames(), commentSelector)))
	}

	return func(c *engine.Codebase) *engine.Query { return c.WhereIs(engine.Neutral(named)) }, nil
}

func selected(named string, judged []contract.Language) error {
	kind, own := strings.CutPrefix(named, "kind:")
	if !own {
		spelling, construct := strings.CutPrefix(named, constructSelector)
		if !construct {
			return nil
		}
		kind = spelling
	}

	written, elsewhere := Written(judged, "kind", kind)
	switch {
	case written:
		return nil
	case len(elsewhere) > 0:
		return fmt.Errorf("select names %s's construct %q, and this rule judges %s", spoken(elsewhere), kind, spoken(judged))
	}

	return fmt.Errorf("no parser of %s writes the construct %q%s", spoken(judged), kind, didYouMean(kind, append(values(judged, "kind"), spellings(judged)...)))
}

const constructSelector = "construct:"

const commentSelector = "comment"

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

func neutralList() string {
	return strings.Join(neutralNames(), ", ")
}

func neutralNames() []string {
	var names []string
	for _, neutral := range engine.Neutrals {
		names = append(names, string(neutral))
	}

	return names
}

func (s Step) checks() int {
	count := 0

	for _, set := range []bool{s.Is != "", s.Kind != "", s.Name != "", s.NameIn != nil, s.NameLike != "",
		s.NameMatches != "", s.NameCase != "", s.NameMood != "", s.Text != nil, s.TextLike != "", s.TextMatches != "", s.Resolves != "",
		s.ResolvesLike != "", s.NamespaceLike != "", s.Layer != "", s.HasModifier != "", s.HasFlag != "", s.WithinLoop != nil,
		s.Operator != "", len(s.OperatorIn) > 0, s.Type != "", s.TypeLike != "",
		len(s.Value) > 0, s.Documented != nil, s.File != "", s.FileMatches != "", s.Position != "", s.Field != "", s.Role != "", s.TopLevel != nil, s.Descendant != nil, s.Child != nil, s.Body != nil, s.Inside != nil,
		s.Documents != nil, s.Next != nil, s.Previous != nil, s.NestedAtLeast != nil, s.Counts != nil, s.Parameters != nil, s.Arguments != nil,
		s.Lines != nil, s.Members != nil, s.Complexity != nil, s.Extends != "", s.ExtendsAny != "", s.Implements != "", s.Uses != "", s.All != nil, s.Any != nil, s.Not != nil, s.HasAttribute != "", s.Sibling != "", s.IsA != "",
		s.TypeKind != "", s.HasAnnotation != "", s.ReturnType != "", s.ParameterType != "", s.Constructs != "", s.Unused != nil,
		s.CalledFrom != "", s.ReferredFrom != nil, s.Calls != nil, s.Argument != nil, s.CommentIs != "", s.DocReference != nil, s.CommentLike != "", s.CommentMatches != "", s.DocTag != "", s.Duplicated != nil, s.Callers != nil, s.Repeated != nil, s.DeclaredBy != "", s.AnsweredBy != nil, s.DeclaredElsewhere != nil, s.SameAs != nil, s.Ancestors != nil, s.RedundantElse != nil, s.UnnamedCondition != nil, s.BindsItsOwnTest != nil, s.SoleLoopBodyGuard != nil, s.PassesItsElement != nil, s.LadderRungs != nil, s.Nullable != nil, s.TypeShape != "", s.TypeOrigin != "", s.ElementType != "",
		s.TestCode != nil, s.Overrides != nil, s.Implicit != nil, s.PHP != "", s.Python != "", s.CSharp != "", s.TypeScript != "", s.Vue != "", s.Rule != ""} {
		if set {
			count++
		}
	}

	return count
}

func (s *Step) prepare() error {
	if err := s.valid(); err != nil {
		return err
	}

	switch {
	case s.NameLike != "":
		s.pattern = glob(s.NameLike)
	case s.TextLike != "":
		s.pattern = glob(s.TextLike)
	case s.ResolvesLike != "":
		s.pattern = glob(strings.TrimPrefix(s.ResolvesLike, `\`))
	case s.TypeLike != "":
		s.pattern = glob(strings.TrimPrefix(s.TypeLike, `\`))
	case s.ElementType != "":
		s.pattern = glob(strings.TrimPrefix(s.ElementType, `\`))
	case s.NamespaceLike != "":
		s.pattern = glob(strings.TrimPrefix(s.NamespaceLike, `\`))
	case s.File != "":
		s.pattern = pathGlob(s.File)
	case s.FileMatches != "":
		return s.compile(s.FileMatches)
	case s.CalledFrom != "":
		s.pattern = pathGlob(s.CalledFrom)
	case s.NameMatches != "":
		return s.compile(s.NameMatches)
	case s.TextMatches != "":
		return s.compile(s.TextMatches)
	case s.ReturnType != "":
		s.pattern = typeGlob(strings.Join(strings.Fields(s.ReturnType), ""))
	case s.ParameterType != "":
		s.pattern = typeGlob(strings.Join(strings.Fields(s.ParameterType), ""))
	case s.Constructs != "":
		s.pattern = glob(strings.TrimPrefix(s.Constructs, `\`))
	case isGlob(s.Extends), isGlob(s.ExtendsAny), isGlob(s.Implements), isGlob(s.Uses), isGlob(s.IsA):
		s.pattern = glob(strings.TrimPrefix(s.Extends+s.ExtendsAny+s.Implements+s.Uses+s.IsA, `\`))
	case s.DeclaredBy != "":
		s.pattern = glob(strings.TrimPrefix(s.DeclaredBy, `\`))
	case s.Ancestors != nil && s.Ancestors.Within != "":
		s.pattern = glob(strings.TrimPrefix(s.Ancestors.Within, `\`))
	case s.CommentLike != "":
		s.pattern = glob(s.CommentLike)
	case s.CommentMatches != "":
		return s.compile(s.CommentMatches)
	}

	for _, nested := range s.nested() {
		if err := nested.prepare(); err != nil {
			return err
		}
	}

	return nil
}

func (s *Step) compile(expression string) error {
	compiled, err := regexp.Compile(expression)
	if err != nil {
		return fmt.Errorf("%s is no regular expression: %w", s.describe(), err)
	}

	s.pattern = compiled

	return nil
}

func (s Step) valid() error {
	if s.checks() != 1 {
		return fmt.Errorf("a step makes one check, and %s makes %d", s.describe(), s.checks())
	}

	if s.Is != "" && !slices.Contains(engine.Neutrals, engine.Neutral(s.Is)) {
		return fmt.Errorf("is %q is no neutral kind: %s%s", s.Is, neutralList(), didYouMean(s.Is, neutralNames()))
	}

	for key, value := range map[string]string{"typeKind": s.TypeKind, "nameCase": s.NameCase, "nameMood": s.NameMood, "position": s.Position,
		"typeShape": s.TypeShape, "typeOrigin": s.TypeOrigin, "role": s.Role, "commentIs": s.CommentIs} {
		if allowed := closedValues[key]; value != "" && !slices.Contains(allowed, value) {
			return errors.New(noneOf(key, value, allowed))
		}
	}

	if err := s.validSizes(); err != nil {
		return err
	}

	if err := s.validValue(); err != nil {
		return err
	}

	if s.DocReference != nil && s.DocReference.Resolves == nil {
		return fmt.Errorf("a docReference asks whether the reference resolves, and %s asks nothing", s.describe())
	}

	if s.Members != nil && s.Members.Step.checks() == 0 && s.Members.Step.Of != "" {
		return fmt.Errorf("a members step that makes no check counts every member, so its of %q judges nothing", s.Members.Step.Of)
	}

	if s.SameAs != nil {
		for key, of := range map[string]string{"sameAs": s.SameAs.Of, "target": s.SameAs.Target} {
			if key == "target" && of == "" {
				continue
			}

			if !slices.ContainsFunc(Targets, func(target Target) bool { return target.Takes(of) }) {
				return errors.New(noneOf(key, of, targetKeys()))
			}
		}
	}

	if s.Descendant != nil && s.Descendant.Within != "" && s.Descendant.Within != ownBody {
		return errors.New(noneOf("within", s.Descendant.Within, []string{ownBody}))
	}

	if s.NestedAtLeast != nil && s.NestedAtLeast.Count < 1 {
		return fmt.Errorf("nestedAtLeast needs a count of 1 or more, and %s has %d", s.describe(), s.NestedAtLeast.Count)
	}

	if closest, found := strings.CutPrefix(s.Of, "closest:"); found && !slices.Contains(engine.Neutrals, engine.Neutral(closest)) {
		return fmt.Errorf("of %q names no neutral kind: %s%s", s.Of, neutralList(), didYouMean(closest, neutralNames()))
	}

	if s.Of != "" && !slices.ContainsFunc(Targets, func(target Target) bool { return target.Takes(s.Of) }) {
		return errors.New(noneOf("of", s.Of, targetKeys()))
	}

	if s.Counts != nil && s.Counts.Distinct != "" && !slices.ContainsFunc(Targets, func(target Target) bool { return target.Takes(s.Counts.Distinct) }) {
		return errors.New(noneOf("distinct", s.Counts.Distinct, targetKeys()))
	}

	return nil
}

func (s Step) validSizes() error {
	named := map[string]*Bounds{}

	for name, bounds := range map[string]*Bounds{"parameters": s.Parameters, "arguments": s.Arguments, "lines": s.Lines,
		"complexity": s.Complexity, "duplicated": s.Duplicated, "callers": s.Callers} {
		if bounds != nil {
			named[name] = bounds
		}
	}

	if s.Counts != nil {
		named["count"] = &s.Counts.Bounds

		if (s.Counts.Descendant == nil) == (s.Counts.Child == nil) {
			return fmt.Errorf("a count counts descendants or children, one of them: %s", s.describe())
		}

		if s.Counts.Field != "" && s.Counts.Child == nil {
			return fmt.Errorf("a count's field narrows its children, and %s counts descendants", s.describe())
		}
	}

	if s.Members != nil {
		named["members"] = &s.Members.Bounds
	}

	if s.Ancestors != nil {
		named["ancestors"] = &s.Ancestors.Bounds
	}

	if s.LadderRungs != nil {
		named["ladderRungs"] = s.LadderRungs
	}

	if s.Repeated != nil {
		named["repeated"] = &s.Repeated.Bounds

		if s.Repeated.Files < 0 {
			return fmt.Errorf("repeated cannot ask for fewer than no files, and %s asks for %d", s.describe(), s.Repeated.Files)
		}

		if s.Repeated.By != "" && s.Repeated.By != owner {
			return fmt.Errorf("repeated groups by %q, and the only thing it groups by is %q: %s", s.Repeated.By, owner, s.describe())
		}
	}

	for name, bounds := range named {
		if err := bounds.valid(name); err != nil {
			return err
		}
	}

	return nil
}

// Ancestors is how long a type's own line of parents may be, and which of them count.
type Ancestors struct {
	Bounds
	Within string `json:"within,omitempty"`
}

// Repeats is how widely a node must be written for the step to keep it: in that many functions, spread over that many files, read the way its choices say.
type Repeats struct {
	Bounds
	Files     int    `json:"files,omitempty"`
	Exact     bool   `json:"exact,omitempty"`
	EitherWay bool   `json:"eitherWay,omitempty"`
	By        string `json:"by,omitempty"`
}

func (r Repeats) reading() engine.Reading {
	return engine.Reading{Exact: r.Exact, EitherWay: r.EitherWay, ByOwner: r.By == owner}
}

func (r Repeats) spread() int {
	if r.Files < 1 {
		return 1
	}

	return r.Files
}

const owner = "owner"

const declaredBy = "declaredBy"

const declarationTarget = "declaration"

const receiverTarget = "receiver"

const (
	conditionTarget     = "condition"
	subjectTarget       = "subject"
	returnTypeTarget    = "returnType"
	parameterTypeTarget = "parameterType"
	annotationTarget    = "annotation:"
)

func (b Bounds) valid(size string) error {
	switch {
	case b.AtLeast == nil && b.AtMost == nil:
		return fmt.Errorf("%s needs atLeast, atMost or both", size)
	case b.AtLeast != nil && *b.AtLeast < 0, b.AtMost != nil && *b.AtMost < 0:
		return fmt.Errorf("%s cannot be bounded below zero", size)
	case b.AtLeast != nil && b.AtMost != nil && *b.AtLeast > *b.AtMost:
		return fmt.Errorf("%s at least %d and at most %d leaves nothing", size, *b.AtLeast, *b.AtMost)
	}

	return nil
}

func (b Bounds) holds(size int) bool {
	return (b.AtLeast == nil || size >= *b.AtLeast) && (b.AtMost == nil || size <= *b.AtMost)
}

func (s Step) describe() string {
	text, _ := json.Marshal(s)

	return string(text)
}

func (s Step) check(match engine.Match) bool {
	subject := s.subject(match)
	if !subject.Exists() {
		return false
	}

	switch {
	case s.Is != "":
		return subject.Is(engine.Neutral(s.Is))
	case s.Kind != "":
		return subject.Kind() == s.Kind || namesTheKind(s.Kind, subject)
	case s.Name != "":
		return nameOf(subject) == s.Name
	case s.NameIn != nil:
		return slices.Contains(s.NameIn, nameOf(subject))
	case s.NameLike != "", s.NameMatches != "":
		name := nameOf(subject)

		return name != "" && s.pattern.MatchString(name)
	case s.NameMood != "":
		return moodOf(s.NameMood, bare(nameOf(subject)))
	case s.NameCase != "":
		return cases[s.NameCase].MatchString(bare(nameOf(subject)))
	case s.Text != nil:
		text, isText := subject.Text()

		return isText && text == *s.Text
	case s.TextLike != "", s.TextMatches != "":
		text, isText := subject.Text()

		return isText && s.pattern.MatchString(text)
	case s.Resolves != "":
		return strings.TrimPrefix(resolved(subject), `\`) == strings.TrimPrefix(s.Resolves, `\`)
	case s.ResolvesLike != "":
		refers := resolved(subject)

		return refers != "" && s.pattern.MatchString(strings.TrimPrefix(refers, `\`))
	case s.NamespaceLike != "":
		namespace := subject.Namespace()

		return namespace != "" && s.pattern.MatchString(namespace)
	case s.Layer != "":
		return s.layers != nil && s.layers.InLayer(subject.Namespace(), s.Layer)
	case s.HasModifier != "":
		return subject.HasModifier(s.HasModifier)
	case s.HasFlag != "":
		return subject.HasFlag(s.HasFlag)
	case s.Type != "":
		return subject.TypeName() == s.Type
	case s.TypeLike != "":
		named := subject.TypeName()

		return named != "" && s.pattern.MatchString(named)
	case s.Operator != "":
		return subject.Operator() == s.Operator
	case len(s.OperatorIn) > 0:
		return slices.Contains(s.OperatorIn, subject.Operator())
	case s.WithinLoop != nil:
		return subject.IsWithinLoop() == *s.WithinLoop
	case s.Documented != nil:
		return subject.IsDocumented() == *s.Documented
	case s.File != "":
		return inFile(s.pattern, subject.FromProject())
	case s.FileMatches != "":
		return s.pattern.MatchString(filepath.ToSlash(subject.FromProject()))
	case s.Position != "":
		return atPosition(subject, s.Position)
	case s.Field != "":
		return subject.Field() == s.Field
	case s.Role != "":
		return subject.Role() == s.Role
	case s.TopLevel != nil:
		return topLevel(subject) == *s.TopLevel
	case s.Descendant != nil:
		return slices.ContainsFunc(s.Descendant.descendants(subject), s.Descendant.check)
	case s.Child != nil:
		return slices.ContainsFunc(s.Child.children(subject), s.Child.check)
	case s.Body != nil:
		return slices.ContainsFunc(subject.Body(), s.Body.check)
	case s.SameAs != nil:
		return s.SameAs.names(subject, s.SameAs.other(subject))
	case s.Inside != nil:
		return slices.ContainsFunc(subject.Ancestors(), s.Inside.check)
	case s.Documents != nil:
		return s.Documents.check(documents(subject))
	case s.Next != nil:
		return s.Next.check(subject.Next())
	case s.Previous != nil:
		return s.Previous.check(subject.Previous())
	case s.NestedAtLeast != nil:
		return s.NestedAtLeast.depth(subject) >= s.NestedAtLeast.Count
	case s.Counts != nil:
		return s.Counts.holds(s.Counts.tally(subject))
	case s.Parameters != nil:
		return subject.Is(engine.Function) && s.Parameters.holds(len(subject.Parameters()))
	case s.Arguments != nil:
		return (subject.Is(engine.Call) || subject.Is(engine.Construction)) && s.Arguments.holds(len(subject.Arguments()))
	case s.Lines != nil:
		return s.Lines.holds(subject.Lines())
	case s.Members != nil:
		return subject.Is(engine.TypeDeclaration) && s.Members.holds(s.Members.tally(subject))
	case s.Complexity != nil:
		return s.Complexity.holds(subject.Complexity())
	case s.Extends != "":
		return s.namesAny(subject.Parents(), s.Extends)
	case s.ExtendsAny != "":
		return s.namesAny(subject.Lineage(), s.ExtendsAny)
	case s.Implements != "":
		return s.namesAny(subject.Contracts(), s.Implements)
	case s.Uses != "":
		return s.namesAny(subject.UsedTraits(), s.Uses)
	case s.IsA != "":
		return s.namesAny(append(append([]string{subject.Class()}, subject.Lineage()...), subject.Contracts()...), s.IsA)
	case s.HasAttribute != "":
		return subject.WritesAttribute(s.HasAttribute)
	case s.Sibling != "":
		return hasSibling(subject, s.Sibling)
	case s.All != nil:
		return !slices.ContainsFunc(s.All, func(step Step) bool { return !step.check(subject) })
	case s.Any != nil:
		return slices.ContainsFunc(s.Any, func(step Step) bool { return step.check(subject) })
	case s.Not != nil:
		return !s.Not.check(subject)
	case s.Rule != "":
		return s.named != nil && s.named.Satisfies(subject)
	case s.TypeKind != "":
		return subject.TypeKind() == s.TypeKind
	case s.HasAnnotation != "":
		return subject.IsAnnotated(s.HasAnnotation)
	case s.ReturnType != "":
		return s.typed(subject.ReturnType())
	case s.ParameterType != "":
		return s.typed(subject.ParameterType())
	case s.Constructs != "":
		return s.constructs(subject)
	case s.Unused != nil:
		return unused(subject) == *s.Unused
	case s.CalledFrom != "":
		return slices.ContainsFunc(subject.Referrers(), func(caller engine.Match) bool { return inFile(s.pattern, caller.FromProject()) })
	case s.ReferredFrom != nil:
		return slices.ContainsFunc(subject.Referrers(), s.ReferredFrom.check)
	case s.Calls != nil:
		return slices.ContainsFunc(subject.OwnDescendants(), func(below engine.Match) bool { return below.Is(engine.Call) && s.Calls.check(below) })
	case s.Argument != nil:
		return s.Argument.passes(subject.Arguments())
	case s.CommentIs != "":
		return s.commentIs(subject)
	case s.DocReference != nil:
		return s.DocReference.holds(subject)
	case s.CommentLike != "", s.CommentMatches != "":
		return s.commented(subject)
	case s.DocTag != "":
		return slices.ContainsFunc(subject.DocTags(), func(tag string) bool { return strings.EqualFold(tag, strings.TrimPrefix(s.DocTag, "@")) })
	case s.Duplicated != nil:
		copies := subject.Copies()

		return copies > 0 && s.Duplicated.holds(copies)
	case s.Callers != nil:
		return s.Callers.holds(len(subject.Referrers()))
	case s.UnnamedCondition != nil:
		return engine.TestsAnUnnamedConcept(subject) == *s.UnnamedCondition
	case s.BindsItsOwnTest != nil:
		return subject.BindsItsOwnTest() == *s.BindsItsOwnTest
	case s.RedundantElse != nil:
		return engine.HasRedundantElse(subject) == *s.RedundantElse
	case s.SoleLoopBodyGuard != nil:
		return engine.IsSoleLoopBodyGuard(subject) == *s.SoleLoopBodyGuard
	case s.PassesItsElement != nil:
		return engine.PassesItsElementOn(subject) == *s.PassesItsElement
	case s.LadderRungs != nil:
		rungs := engine.SubjectLadderLength(subject)

		return rungs > 0 && s.LadderRungs.holds(rungs)
	case s.Ancestors != nil:
		return s.Ancestors.holds(len(s.ancestors(subject)))
	case s.Repeated != nil:
		functions, files := subject.Repeats(s.Repeated.reading())

		return functions > 0 && s.Repeated.holds(functions) && files >= s.Repeated.spread()
	case s.DeclaredBy != "":
		declarer := subject.Declarer()

		return declarer != "" && s.pattern.MatchString(declarer)
	case s.DeclaredElsewhere != nil:
		return subject.DeclaredElsewhere() == *s.DeclaredElsewhere
	case s.AnsweredBy != nil:
		return (subject.Answering(engine.Reading{EitherWay: true}) != "") == *s.AnsweredBy
	case s.TypeShape != "":
		typed := subject.Typed()

		return typed != nil && typed.Kind == s.TypeShape
	case s.TypeOrigin != "":
		typed := subject.Typed()

		return typed != nil && typed.Origin == s.TypeOrigin
	case s.ElementType != "":
		return s.elementNamed(subject)
	case len(s.Value) > 0:
		return valueHolds(s.Value, subject)
	case s.Nullable != nil:
		return subject.Typed() != nil && subject.AdmitsAbsence() == *s.Nullable
	case s.Overrides != nil:
		return subject.IsOverride() == *s.Overrides
	case s.Implicit != nil:
		return subject.IsImplicit() == *s.Implicit
	case s.TestCode != nil:
		return subject.IsTest() == *s.TestCode
	case s.PHP != "", s.Python != "", s.CSharp != "", s.TypeScript != "", s.Vue != "":
		return s.predicate != nil && s.predicate(subject)
	}

	return false
}

func (s Step) constructs(subject engine.Match) bool {
	for _, below := range append([]engine.Match{subject}, subject.OwnDescendants()...) {
		if created := below.Constructed(); created.Exists() && s.namesPattern(created) {
			return true
		}
	}

	return false
}

func (s Step) namesPattern(named engine.Match) bool {
	symbol := engine.BareSymbol(named.Named())
	if !strings.ContainsAny(s.Constructs, `\.`) {
		symbol = engine.LastPart(symbol)
	}

	return s.pattern.MatchString(symbol)
}

func unused(declaration engine.Match) bool {
	switch {
	case declaration.DeclaresAField():
		return declaration.IsUnreadMember()
	case declaration.Is(engine.Parameter):
		return declaration.IsUnread()
	case declaration.Is(engine.Function), declaration.Is(engine.TypeDeclaration):
		return declaration.Node().Symbol != "" && !declaration.IsImplicit() && !declaration.IsOverride() && len(declaration.Referrers()) == 0
	case declaration.Role() == "member":
		return declaration.IsUnreadMember()
	}

	return false
}

func (a Argued) passes(arguments []engine.Match) bool {
	at := a.At
	if at < 0 {
		at += len(arguments)
	}

	if at < 0 || at >= len(arguments) {
		return false
	}

	return a.check(arguments[at])
}

func (s Step) own() (contract.Language, string) {
	for language, name := range map[contract.Language]string{contract.PHP: s.PHP, contract.Python: s.Python,
		contract.CSharp: s.CSharp, contract.TypeScript: s.TypeScript, contract.Vue: s.Vue} {
		if name != "" {
			return language, name
		}
	}

	return "", ""
}

func (s *Step) ownChecks(judged []contract.Language) error {
	if language, name := s.own(); language != "" {
		if !slices.Contains(judged, language) {
			return fmt.Errorf("a %s check needs a rule that judges %s", language, language)
		}

		predicate, offered := engine.PredicateOf(language, name)
		if offered {
			s.predicate = predicate.Holds
		}

		if !offered {
			var names []string
			for _, predicate := range engine.PredicatesOf(language) {
				names = append(names, predicate.Name)
			}

			return fmt.Errorf("%s offers no check %q: it offers %s%s", language, name, strings.Join(names, ", "), didYouMean(name, names))
		}
	}

	for _, nested := range s.nested() {
		if err := nested.ownChecks(judged); err != nil {
			return err
		}
	}

	return nil
}

func (s *Step) vocabularyKeys() map[string]string {
	return map[string]string{"kind": s.Kind, "field": s.Field, "operator": s.Operator, "hasFlag": s.HasFlag,
		"hasModifier": s.HasModifier}
}

func Written(judged []contract.Language, key, named string) (seen bool, elsewhere []contract.Language) {
	return offered(judged, key, named), owning(key, named)
}

func offered(judged []contract.Language, key, named string) bool {
	for _, language := range judged {
		vocabulary, held := VocabularyOf(language)
		if !held || !vocabulary.DeclaresKey(key) {
			return true
		}

		if slices.Contains(valuesOf(vocabulary, key), named) {
			return true
		}

		if _, spelled := vocabulary.Spelled(named); key == "kind" && spelled {
			return true
		}
	}

	return false
}

func (s *Step) declaredValues(judged []contract.Language) error {
	for _, key := range slices.Sorted(maps.Keys(s.vocabularyKeys())) {
		named := s.vocabularyKeys()[key]
		if named == "" {
			continue
		}

		written, elsewhere := Written(judged, key, named)
		if written {
			continue
		}

		if len(elsewhere) > 0 {
			return fmt.Errorf("%s %q is %s's, and this rule judges %s", key, named, spoken(elsewhere), spoken(judged))
		}

		return fmt.Errorf("no parser of %s writes the %s %q%s", spoken(judged), key, named, didYouMean(named, values(judged, key)))
	}

	for _, nested := range s.nested() {
		if err := nested.declaredValues(judged); err != nil {
			return err
		}
	}

	return nil
}

func spoken(languages []contract.Language) string {
	var names []string
	for _, language := range languages {
		names = append(names, string(language))
	}

	return strings.Join(names, " and ")
}

func owning(key, named string) []contract.Language {
	var owners []contract.Language
	for _, language := range contract.Languages {
		vocabulary, held := VocabularyOf(language)
		if !held {
			continue
		}

		_, spelled := vocabulary.Spelled(named)
		if slices.Contains(valuesOf(vocabulary, key), named) || (key == "kind" && spelled) {
			owners = append(owners, language)
		}
	}

	return owners
}

func values(judged []contract.Language, key string) []string {
	var offered []string
	for _, language := range judged {
		if vocabulary, held := VocabularyOf(language); held {
			offered = append(offered, valuesOf(vocabulary, key)...)
		}
	}

	return offered
}

func spellings(judged []contract.Language) []string {
	var derived []string
	for _, language := range judged {
		if vocabulary, held := VocabularyOf(language); held {
			derived = append(derived, vocabulary.Spellings()...)
		}
	}

	return derived
}

func valuesOf(vocabulary Vocabulary, key string) []string {
	switch key {
	case "kind":
		return vocabulary.Kinds()
	case "field":
		return vocabulary.Fields()
	case "operator":
		return vocabulary.Operators()
	case "hasFlag":
		return vocabulary.Flags()
	case "hasModifier":
		return vocabulary.Modifiers()
	}

	return nil
}

func said(languages []contract.Language) string {
	var names []string
	for _, language := range languages {
		names = append(names, string(language))
	}

	return strings.Join(names, ", ")
}

// commented says whether a comment on the node, or in the run directly above it, matches the step's pattern.
type Referenced struct {
	Resolves *bool `json:"resolves"`
}

func (r Referenced) holds(subject engine.Match) bool {
	for _, comment := range commentsOf(subject) {
		for _, ref := range comment.Refs {
			if engine.Dangling(ref) == !*r.Resolves {
				return true
			}
		}
	}

	return false
}

func (s Step) commentIs(subject engine.Match) bool {
	for _, comment := range commentsOf(subject) {
		if comment.Is(s.CommentIs) {
			return true
		}
	}

	return false
}

func documents(subject engine.Match) engine.Match {
	comment, is := subject.AsComment()
	if !is {
		return engine.Match{}
	}

	return comment.Documents()
}

func commentsOf(subject engine.Match) []engine.Commented {
	if comment, ok := subject.AsComment(); ok {
		return []engine.Commented{comment}
	}

	var comments []engine.Commented
	for _, comment := range append(subject.Comments(), subject.CommentsAbove()...) {
		comments = append(comments, engine.Commented{Comment: comment, File: subject.Source()})
	}

	return comments
}

func (s Step) commented(subject engine.Match) bool {
	if subject.Kind() == engine.CommentKind {
		return s.pattern.MatchString(subject.Written())
	}

	for _, comment := range append(subject.Comments(), subject.CommentsAbove()...) {
		if s.pattern.MatchString(comment.Text) {
			return true
		}
	}

	return false
}

func (s Step) namesAny(symbols []string, want string) bool {
	if !isGlob(want) {
		return slices.ContainsFunc(symbols, func(symbol string) bool { return engine.NamesType(symbol, want) })
	}

	return slices.ContainsFunc(symbols, func(symbol string) bool {
		symbol = strings.TrimPrefix(symbol, `\`)

		return s.pattern.MatchString(symbol) || s.pattern.MatchString(engine.LastPart(symbol))
	})
}

func hasSibling(node engine.Match, pattern string) bool {
	if node.Codebase() == nil {
		return false
	}
	own := node.Judged()
	folder := path.Dir(own)
	pattern = strings.ReplaceAll(pattern, "{folder}", path.Base(folder))
	where := path.Clean(path.Join(folder, path.Dir(pattern)))
	wanted := glob(path.Base(pattern))
	for _, file := range node.Codebase().Files() {
		judged := file.Judged()
		if judged != own && path.Dir(judged) == where && wanted.MatchString(path.Base(judged)) {
			return true
		}
	}

	return false
}

func isGlob(want string) bool {
	return strings.ContainsAny(want, "*?[")
}

func resolved(node engine.Match) string {
	if refers := node.Refers(); refers != "" {
		return refers
	}

	return node.Class()
}

func (s Step) typed(written engine.Match) bool {
	if !written.Exists() {
		return false
	}

	if s.pattern.MatchString(strings.Join(strings.Fields(written.Written()), "")) {
		return true
	}

	refers := strings.TrimPrefix(written.Refers(), `\`)

	return refers != "" && s.pattern.MatchString(refers)
}

func (s Step) elementNamed(subject engine.Match) bool {
	element := subject.ElementType()
	if element == nil {
		return false
	}

	for _, named := range []string{element.Name, element.Text} {
		if named != "" && s.pattern.MatchString(strings.TrimLeft(named, `?\`)) {
			return true
		}
	}

	return false
}

func typeGlob(pattern string) *regexp.Regexp {
	parts := strings.Split(pattern, "*")
	for i, part := range parts {
		parts[i] = regexp.QuoteMeta(part)
	}

	return regexp.MustCompile("^" + strings.Join(parts, ".*") + "$")
}

func (c Count) tally(match engine.Match) int {
	counted, step := match.Descendants(), c.Descendant

	if c.Child != nil {
		counted, step = match.Children(), c.Child

		if c.Field != "" {
			counted = match.ChildrenIn(c.Field)
		}
	}

	count := 0
	seen := map[string]bool{}
	for _, node := range counted {
		if !step.check(node) {
			continue
		}
		if c.Distinct == "" {
			count++

			continue
		}
		if written := (Step{Of: c.Distinct}).subject(node).Written(); written != "" && !seen[written] {
			seen[written] = true
			count++
		}
	}

	return count
}

func (t Tally) tally(match engine.Match) int {
	count := 0
	members := match.Members()
	if t.Inherited {
		members = append(members, match.InheritedMembers()...)
	}
	for _, member := range members {
		if t.Step.checks() == 0 || t.check(member) {
			count++
		}
	}

	return count
}

func (n Nesting) depth(match engine.Match) int {
	count := 0

	for _, node := range append([]engine.Match{match}, match.Ancestors()...) {
		passes := n.check(node)
		if node.Is(engine.Function) && !passes && node.Node() != match.Node() {
			break
		}

		if passes && !node.IsContinuation() {
			count++
		}
	}

	return count
}

func atPosition(match engine.Match, position string) bool {
	first, last := !match.Previous().Exists(), !match.Next().Exists()

	switch position {
	case "first":
		return first
	case "last":
		return last
	default:
		return first && last
	}
}

func topLevel(match engine.Match) bool {
	return !match.Closest(engine.Function).Exists() && !match.EnclosingType().Exists()
}

var moods = []string{"bare-verb", "third-person", "question"}

func moodOf(mood, name string) bool {
	switch mood {
	case "bare-verb":
		return prose.IsBareVerb(name)
	case "third-person":
		return prose.IsThirdPerson(name)
	case "question":
		return prose.ReadsAsQuestion(name)
	}

	return false
}

func nameOf(match engine.Match) string {
	if name := match.DeclaredName(); name != "" {
		return name
	}

	return match.CalleeName()
}

func bare(name string) string {
	return strings.TrimLeft(name, "$_")
}

func glob(pattern string) *regexp.Regexp {
	var expression strings.Builder
	expression.WriteString("(?s)^")

	for at := 0; at < len(pattern); at++ {
		switch pattern[at] {
		case '*':
			expression.WriteString(".*")
		case '?':
			expression.WriteString(".")
		case '[':
			at = writeBracket(&expression, pattern, at)
		default:
			expression.WriteString(regexp.QuoteMeta(pattern[at : at+1]))
		}
	}

	expression.WriteString("$")

	return regexp.MustCompile(expression.String())
}

func writeBracket(expression *strings.Builder, pattern string, at int) int {
	end := strings.IndexByte(pattern[at+1:], ']')
	if end < 1 {
		expression.WriteString(regexp.QuoteMeta("["))

		return at
	}
	class := pattern[at+1 : at+1+end]
	if strings.HasPrefix(class, "!") {
		class = "^" + class[1:]
	}
	expression.WriteString("[" + strings.ReplaceAll(class, `\`, `\\`) + "]")

	return at + 1 + end
}

func pathGlob(pattern string) *regexp.Regexp {
	var expression strings.Builder
	expression.WriteString("^")

	for at := 0; at < len(pattern); at++ {
		switch {
		case strings.HasPrefix(pattern[at:], "**/"):
			expression.WriteString("(.*/)?")
			at += 2
		case strings.HasPrefix(pattern[at:], "**"):
			expression.WriteString(".*")
			at++
		case pattern[at] == '*':
			expression.WriteString("[^/]*")
		case pattern[at] == '?':
			expression.WriteString("[^/]")
		case pattern[at] == '[':
			at = writeBracket(&expression, pattern, at)
		default:
			expression.WriteString(regexp.QuoteMeta(pattern[at : at+1]))
		}
	}

	expression.WriteString("$")

	return regexp.MustCompile(expression.String())
}

func (s Step) subject(match engine.Match) engine.Match {
	switch {
	case s.Of == "parent":
		return match.Parent()
	case s.Of == "enclosingFunction":
		return match.EnclosingFunction()
	case s.Of == "enclosingType":
		return match.EnclosingType()
	case s.Of == "root":
		return match.Root()
	case s.Of == declaredBy:
		return match.Declaring()
	case s.Of == declarationTarget:
		return match.Declared()
	case s.Of == receiverTarget:
		return match.Receiver()
	case s.Of == conditionTarget:
		return match.Condition()
	case s.Of == subjectTarget:
		return match.ComparisonSubject()
	case strings.HasPrefix(s.Of, annotationTarget):
		return match.Annotated(strings.TrimPrefix(s.Of, annotationTarget))
	case s.Of == returnTypeTarget:
		return match.ReturnType()
	case s.Of == parameterTypeTarget:
		return match.ParameterType()
	case strings.HasPrefix(s.Of, "closest:"):
		return match.Closest(engine.Neutral(strings.TrimPrefix(s.Of, "closest:")))
	case strings.HasPrefix(s.Of, "child:"):
		return match.Child(strings.TrimPrefix(s.Of, "child:"))
	default:
		return match
	}
}

func (s Step) ancestors(subject engine.Match) []string {
	line := subject.Lineage()
	if s.Ancestors.Within == "" {
		return line
	}

	var within []string
	for _, class := range line {
		if s.pattern.MatchString(class) {
			within = append(within, class)
		}
	}

	return within
}

func inFile(pattern *regexp.Regexp, file string) bool {
	segments := strings.Split(strings.ReplaceAll(file, `\`, "/"), "/")

	for i := range segments {
		if pattern.MatchString(strings.Join(segments[i:], "/")) {
			return true
		}
	}

	return false
}

// ownSin is a sin a project's rule declares.
type ownSin struct {
	definition sins.Definition
}

func (s ownSin) Definition() sins.Definition {
	return s.definition
}
