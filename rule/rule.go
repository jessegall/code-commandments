// Package rule is a detector a project writes as data: the engine it judges, the sin it finds, and the query
// that finds it, composed from the same selectors and checks a shipped detector composes in code.
package rule

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
	"github.com/jessegall/code-commandments/skill"
)

// languages are the languages each engine judges.
var languages = map[catalog.Engine][]contract.Language{
	catalog.Backend:    {contract.PHP},
	catalog.Frontend:   {contract.Vue},
	catalog.TypeScript: {contract.TypeScript, contract.Vue},
	catalog.Python:     {contract.Python},
	catalog.CSharp:     {contract.CSharp},
}

// cases are the naming styles nameCase tells apart, each the whole name.
var cases = map[string]*regexp.Regexp{
	"camel":  regexp.MustCompile(`^[a-z][a-z0-9]*([A-Z][a-z0-9]*)*$`),
	"pascal": regexp.MustCompile(`^[A-Z][A-Za-z0-9]*[a-z][A-Za-z0-9]*$`),
	"snake":  regexp.MustCompile(`^[a-z][a-z0-9]*(_[a-z0-9]+)*$`),
	"upper":  regexp.MustCompile(`^[A-Z][A-Z0-9]*(_[A-Z0-9]+)*$`),
	"kebab":  regexp.MustCompile(`^[a-z][a-z0-9]*(-[a-z0-9]+)*$`),
}

// file is a rule as it is written.
type file struct {
	Schema string  `json:"$schema,omitempty"`
	Engine string  `json:"engine"`
	Sin    sinFile `json:"sin"`
	Find   find    `json:"find"`
}

type sinFile struct {
	Name        string `json:"name"`
	Description string `json:"description"`
	Rule        string `json:"rule,omitempty"`
	Suggestion  string `json:"suggestion,omitempty"`
	Skill       string `json:"skill"`
}

type find struct {
	Select string `json:"select"`
	Where  []Step `json:"where,omitempty"`
	Reject []Step `json:"reject,omitempty"`
}

// Step is one check of a rule's query, on the node itself or, with Of or a nested step, on one related to it.
type Step struct {
	Is             string   `json:"is,omitempty"`
	Kind           string   `json:"kind,omitempty"`
	Name           string   `json:"name,omitempty"`
	NameIn         []string `json:"nameIn,omitempty"`
	NameLike       string   `json:"nameLike,omitempty"`
	NameMatches    string   `json:"nameMatches,omitempty"`
	NameCase       string   `json:"nameCase,omitempty"`
	Text           *string  `json:"text,omitempty"`
	TextLike       string   `json:"textLike,omitempty"`
	TextMatches    string   `json:"textMatches,omitempty"`
	Resolves       string   `json:"resolves,omitempty"`
	ResolvesLike   string   `json:"resolvesLike,omitempty"`
	NamespaceLike  string   `json:"namespaceLike,omitempty"`
	Layer          string   `json:"layer,omitempty"`
	HasModifier    string   `json:"hasModifier,omitempty"`
	HasFlag        string   `json:"hasFlag,omitempty"`
	WithinLoop     *bool    `json:"withinLoop,omitempty"`
	Documented     *bool    `json:"documented,omitempty"`
	File           string   `json:"file,omitempty"`
	Position       string   `json:"position,omitempty"`
	TopLevel       *bool    `json:"topLevel,omitempty"`
	Of             string   `json:"of,omitempty"`
	Descendant     *Step    `json:"descendant,omitempty"`
	Inside         *Step    `json:"inside,omitempty"`
	Next           *Step    `json:"next,omitempty"`
	Previous       *Step    `json:"previous,omitempty"`
	NestedAtLeast  *Nesting `json:"nestedAtLeast,omitempty"`
	Counts         *Count   `json:"count,omitempty"`
	Parameters     *Bounds  `json:"parameters,omitempty"`
	Arguments      *Bounds  `json:"arguments,omitempty"`
	Lines          *Bounds  `json:"lines,omitempty"`
	Members        *Tally   `json:"members,omitempty"`
	Complexity     *Bounds  `json:"complexity,omitempty"`
	Extends        string   `json:"extends,omitempty"`
	ExtendsAny     string   `json:"extendsAny,omitempty"`
	Implements     string   `json:"implements,omitempty"`
	TypeKind       string   `json:"typeKind,omitempty"`
	HasAnnotation  string   `json:"hasAnnotation,omitempty"`
	ReturnType     string   `json:"returnType,omitempty"`
	ParameterType  string   `json:"parameterType,omitempty"`
	Constructs     string   `json:"constructs,omitempty"`
	Unused         *bool    `json:"unused,omitempty"`
	CalledFrom     string   `json:"calledFrom,omitempty"`
	Calls          *Step    `json:"calls,omitempty"`
	Argument       *Argued  `json:"argument,omitempty"`
	CommentLike    string   `json:"commentLike,omitempty"`
	CommentMatches string   `json:"commentMatches,omitempty"`
	DocTag         string   `json:"docTag,omitempty"`
	Duplicated     *Bounds  `json:"duplicated,omitempty"`
	TestCode       *bool    `json:"testCode,omitempty"`
	PHP            string   `json:"php,omitempty"`
	Python         string   `json:"python,omitempty"`
	CSharp         string   `json:"csharp,omitempty"`
	TypeScript     string   `json:"typescript,omitempty"`
	Vue            string   `json:"vue,omitempty"`

	// pattern is the step's glob or regular expression, compiled once when the rule is read.
	pattern *regexp.Regexp

	// layers are the layers the project declares for the rule's engine, which a layer step looks its name up in.
	layers *engine.LayerStack

	// predicate is the language's own check the step names, found once when the rule is read.
	predicate func(engine.Match) bool
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

// Count counts the nodes below one that pass a step: its descendants, or its children, those filling one
// field when Field names it.
type Count struct {
	Descendant *Step  `json:"descendant,omitempty"`
	Child      *Step  `json:"child,omitempty"`
	Field      string `json:"field,omitempty"`
	Bounds
}

// Argued is a step on one of a call's arguments: the one At places from the first, or from the last when At is
// negative, -1 being the last.
type Argued struct {
	Step
	At int `json:"at"`
}

// Tally counts the members of a type that pass its step, or every member when it makes no check.
type Tally struct {
	Step
	Bounds
}

// layered are the engines a project declares dependency layers for, and how each spells a namespace: the
// separator between its parts and whether its case matters, as that engine's dependency detector reads them.
var layered = map[catalog.Engine]struct {
	separator     string
	caseSensitive bool
}{
	catalog.Backend: {`\`, false},
	catalog.Python:  {".", true},
	catalog.CSharp:  {".", true},
}

// Rule is a project's own detector.
type Rule struct {
	name   string
	engine catalog.Engine
	sin    ownSin
	find   find
}

// Skills finds the skill a rule's sin names: the project's own, else a shipped one.
type Skills func(slug string) (skill.Skill, bool)

// Parse reads the rule named name, finding its sin's skill through skills. A rule the tool cannot run says
// which part is wrong.
func Parse(name string, text []byte, skills Skills) (Rule, error) {
	decoder := json.NewDecoder(bytes.NewReader(text))
	decoder.DisallowUnknownFields()

	var read file
	if err := decoder.Decode(&read); err != nil {
		if key, unknown := unknownKey(err); unknown {
			return Rule{}, fmt.Errorf("%s: no check or key is called %q%s", name, key, didYouMean(key, knownKeys()))
		}

		return Rule{}, fmt.Errorf("%s: %w", name, err)
	}

	if decoder.More() {
		return Rule{}, fmt.Errorf("%s: the rule is one JSON object, and more follows it", name)
	}

	engine := catalog.Engine(read.Engine)
	if _, known := languages[engine]; !known {
		return Rule{}, fmt.Errorf("%s: %s", name, noneOf("engine", read.Engine, engineNames()))
	}

	if read.Sin.Name == "" || read.Sin.Skill == "" {
		return Rule{}, fmt.Errorf("%s: a sin needs a name and the skill that teaches its fix", name)
	}

	taught, found := skills(read.Sin.Skill)
	if !found {
		return Rule{}, fmt.Errorf("%s: no skill %q, among the project's own or the shipped ones", name, read.Sin.Skill)
	}

	if _, err := selector(read.Find.Select); err != nil {
		return Rule{}, fmt.Errorf("%s: %w", name, err)
	}

	for _, steps := range [][]Step{read.Find.Where, read.Find.Reject} {
		for i := range steps {
			if err := steps[i].prepare(); err != nil {
				return Rule{}, fmt.Errorf("%s: %w", name, err)
			}

			if err := steps[i].ownChecks(languages[engine]); err != nil {
				return Rule{}, fmt.Errorf("%s: %w", name, err)
			}

			if _, declares := layered[engine]; !declares && steps[i].usesLayers() {
				return Rule{}, fmt.Errorf("%s: a layer step needs declared layers, and the %s engine has none", name, engine)
			}
		}
	}

	return Rule{name, engine, ownSin{sins.Definition{
		Name:        read.Sin.Name,
		Skill:       taught,
		Description: read.Sin.Description,
		Rule:        read.Sin.Rule,
		Suggestion:  read.Sin.Suggestion,
	}}, read.Find}, nil
}

// WithLayers is the rule looking a layer step's name up in the layers the project declares for its engine.
func (r Rule) WithLayers(declared map[string][]string) Rule {
	spelling, declares := layered[r.engine]
	if !declares {
		return r
	}

	stack := engine.Layers(declared, spelling.separator, spelling.caseSensitive)
	r.find.Where = withLayers(r.find.Where, &stack)
	r.find.Reject = withLayers(r.find.Reject, &stack)

	return r
}

// withLayers are copies of the steps, and of every step nested in them, that look layers up in the stack.
func withLayers(steps []Step, stack *engine.LayerStack) []Step {
	copied := make([]Step, len(steps))

	for i, step := range steps {
		copied[i] = step.withLayers(stack)
	}

	return copied
}

// withLayers is a copy of the step, and of every step nested in it, that looks layers up in the stack.
func (s Step) withLayers(stack *engine.LayerStack) Step {
	s.layers = stack

	for _, nested := range []**Step{&s.Descendant, &s.Inside, &s.Next, &s.Previous, &s.Calls} {
		if *nested != nil {
			copied := (*nested).withLayers(stack)
			*nested = &copied
		}
	}

	if s.NestedAtLeast != nil {
		counted := *s.NestedAtLeast
		counted.Step = counted.Step.withLayers(stack)
		s.NestedAtLeast = &counted
	}

	if s.Counts != nil {
		counted := *s.Counts
		for _, nested := range []**Step{&counted.Descendant, &counted.Child} {
			if *nested != nil {
				copied := (*nested).withLayers(stack)
				*nested = &copied
			}
		}
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

	return s
}

// nested are the steps nested in this one.
func (s *Step) nested() []*Step {
	var nested []*Step

	for _, step := range []*Step{s.Descendant, s.Inside, s.Next, s.Previous, s.Calls} {
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

	return nested
}

// usesLayers says whether the step, or one nested in it, is a layer step.
func (s Step) usesLayers() bool {
	return s.Layer != "" || slices.ContainsFunc(s.nested(), (*Step).usesLayers)
}

// Name is the detector's name: the rule file's.
func (r Rule) Name() string {
	return r.name
}

// Engine is the engine the rule judges.
func (r Rule) Engine() catalog.Engine {
	return r.engine
}

// Sin is the sin the rule finds.
func (r Rule) Sin() sins.Sin {
	return r.sin
}

// Find runs the rule's query over the files of its engine.
func (r Rule) Find(codebase *engine.Codebase) []engine.Match {
	selected, _ := selector(r.find.Select)
	query := selected(codebase.Of(languages[r.engine]...))

	for _, step := range r.find.Where {
		query = query.Where(step.check)
	}

	for _, step := range r.find.Reject {
		query = query.Reject(step.check)
	}

	return query.Get()
}

// selector opens the query a rule's select names: a neutral kind, or `kind:` and a language's own kind.
func selector(named string) (func(*engine.Codebase) *engine.Query, error) {
	if kind, own := strings.CutPrefix(named, "kind:"); own && kind != "" {
		return func(c *engine.Codebase) *engine.Query { return c.WhereKind(kind) }, nil
	}

	if !slices.Contains(engine.Neutrals, engine.Neutral(named)) {
		return nil, fmt.Errorf("select %q is neither a neutral kind (%s) nor kind:<Kind>%s", named, neutralList(), didYouMean(named, neutralNames()))
	}

	return func(c *engine.Codebase) *engine.Query { return c.WhereIs(engine.Neutral(named)) }, nil
}

// noneOf says a key's value is none of the ones it may take, naming the nearest when it is a slip of the keyboard.
func noneOf(key, value string, allowed []string) string {
	return fmt.Sprintf("%s %q is none of %s%s", key, value, strings.Join(allowed, ", "), didYouMean(value, allowed))
}

// engineNames are the names of every engine a rule may judge.
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

// checks counts the checks a step makes; a step makes exactly one.
func (s Step) checks() int {
	count := 0

	for _, set := range []bool{s.Is != "", s.Kind != "", s.Name != "", s.NameIn != nil, s.NameLike != "",
		s.NameMatches != "", s.NameCase != "", s.Text != nil, s.TextLike != "", s.TextMatches != "", s.Resolves != "",
		s.ResolvesLike != "", s.NamespaceLike != "", s.Layer != "", s.HasModifier != "", s.HasFlag != "", s.WithinLoop != nil,
		s.Documented != nil, s.File != "", s.Position != "", s.TopLevel != nil, s.Descendant != nil, s.Inside != nil,
		s.Next != nil, s.Previous != nil, s.NestedAtLeast != nil, s.Counts != nil, s.Parameters != nil, s.Arguments != nil,
		s.Lines != nil, s.Members != nil, s.Complexity != nil, s.Extends != "", s.ExtendsAny != "", s.Implements != "",
		s.TypeKind != "", s.HasAnnotation != "", s.ReturnType != "", s.ParameterType != "", s.Constructs != "", s.Unused != nil,
		s.CalledFrom != "", s.Calls != nil, s.Argument != nil, s.CommentLike != "", s.CommentMatches != "", s.DocTag != "", s.Duplicated != nil,
		s.TestCode != nil, s.PHP != "", s.Python != "", s.CSharp != "", s.TypeScript != "", s.Vue != ""} {
		if set {
			count++
		}
	}

	return count
}

// prepare checks the step can run, and compiles its glob or regular expression once.
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
	case s.NamespaceLike != "":
		s.pattern = glob(strings.TrimPrefix(s.NamespaceLike, `\`))
	case s.File != "":
		s.pattern = pathGlob(s.File)
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

// compile reads a regular expression, saying which one when it cannot.
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

	for key, value := range map[string]string{"typeKind": s.TypeKind, "nameCase": s.NameCase, "position": s.Position} {
		if allowed := closedValues[key]; value != "" && !slices.Contains(allowed, value) {
			return errors.New(noneOf(key, value, allowed))
		}
	}

	if err := s.validSizes(); err != nil {
		return err
	}

	if s.Members != nil && s.Members.Step.checks() == 0 && s.Members.Step.Of != "" {
		return fmt.Errorf("a members step that makes no check counts every member, so its of %q judges nothing", s.Members.Step.Of)
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

	return nil
}

// validSizes checks every size the step bounds is bounded honestly, and a count says what it counts.
func (s Step) validSizes() error {
	named := map[string]*Bounds{}

	for name, bounds := range map[string]*Bounds{"parameters": s.Parameters, "arguments": s.Arguments, "lines": s.Lines,
		"complexity": s.Complexity, "duplicated": s.Duplicated} {
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

	for name, bounds := range named {
		if err := bounds.valid(name); err != nil {
			return err
		}
	}

	return nil
}

// valid checks the bounds bound something, below zero nowhere, and leave room between them.
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

// holds says whether the size lies within the bounds.
func (b Bounds) holds(size int) bool {
	return (b.AtLeast == nil || size >= *b.AtLeast) && (b.AtMost == nil || size <= *b.AtMost)
}

func (s Step) describe() string {
	text, _ := json.Marshal(s)

	return string(text)
}

// check answers the step for a node: on the node the step is about, which is the one given unless Of names
// another.
func (s Step) check(match engine.Match) bool {
	subject := s.subject(match)
	if !subject.Exists() {
		return false
	}

	switch {
	case s.Is != "":
		return subject.Is(engine.Neutral(s.Is))
	case s.Kind != "":
		return subject.Kind() == s.Kind
	case s.Name != "":
		return nameOf(subject) == s.Name
	case s.NameIn != nil:
		return slices.Contains(s.NameIn, nameOf(subject))
	case s.NameLike != "", s.NameMatches != "":
		name := nameOf(subject)

		return name != "" && s.pattern.MatchString(name)
	case s.NameCase != "":
		return cases[s.NameCase].MatchString(bare(nameOf(subject)))
	case s.Text != nil:
		text, isText := subject.Text()

		return isText && text == *s.Text
	case s.TextLike != "", s.TextMatches != "":
		text, isText := subject.Text()

		return isText && s.pattern.MatchString(text)
	case s.Resolves != "":
		return strings.TrimPrefix(subject.Refers(), `\`) == strings.TrimPrefix(s.Resolves, `\`)
	case s.ResolvesLike != "":
		refers := subject.Refers()

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
	case s.WithinLoop != nil:
		return subject.IsWithinLoop() == *s.WithinLoop
	case s.Documented != nil:
		return subject.IsDocumented() == *s.Documented
	case s.File != "":
		return inFile(s.pattern, subject.Judged())
	case s.Position != "":
		return atPosition(subject, s.Position)
	case s.TopLevel != nil:
		return topLevel(subject) == *s.TopLevel
	case s.Descendant != nil:
		return slices.ContainsFunc(subject.Descendants(), s.Descendant.check)
	case s.Inside != nil:
		return slices.ContainsFunc(subject.Ancestors(), s.Inside.check)
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
		return slices.ContainsFunc(subject.Extends(), func(parent engine.Match) bool { return parent.Names(s.Extends) })
	case s.ExtendsAny != "":
		return subject.Is(engine.TypeDeclaration) && namesAny(subject.Lineage(), s.ExtendsAny)
	case s.Implements != "":
		return subject.Is(engine.TypeDeclaration) && namesAny(subject.Contracts(), s.Implements)
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
		return slices.ContainsFunc(subject.Referrers(), func(caller engine.Match) bool { return inFile(s.pattern, caller.Judged()) })
	case s.Calls != nil:
		return slices.ContainsFunc(subject.OwnDescendants(), func(below engine.Match) bool { return below.Is(engine.Call) && s.Calls.check(below) })
	case s.Argument != nil:
		return (subject.Is(engine.Call) || subject.Is(engine.Construction)) && s.Argument.passes(subject.Arguments())
	case s.CommentLike != "", s.CommentMatches != "":
		return s.commented(subject)
	case s.DocTag != "":
		return slices.ContainsFunc(subject.DocTags(), func(tag string) bool { return strings.EqualFold(tag, strings.TrimPrefix(s.DocTag, "@")) })
	case s.Duplicated != nil:
		copies := subject.Copies()

		return copies > 0 && s.Duplicated.holds(copies)
	case s.TestCode != nil:
		return subject.IsTest() == *s.TestCode
	case s.PHP != "", s.Python != "", s.CSharp != "", s.TypeScript != "", s.Vue != "":
		return s.predicate != nil && s.predicate(subject)
	}

	return false
}

// constructs says whether the construction creates an instance of a type the pattern names, or, on anything
// else, whether a construction of its own does. A qualified pattern is matched by the whole symbol the type
// resolves to, a bare one by its last part.
func (s Step) constructs(subject engine.Match) bool {
	for _, below := range append([]engine.Match{subject}, subject.OwnDescendants()...) {
		if created := below.Constructed(); created.Exists() && s.namesPattern(created) {
			return true
		}
	}

	return false
}

// namesPattern says whether the type the node names matches the step's pattern.
func (s Step) namesPattern(named engine.Match) bool {
	symbol := engine.BareSymbol(named.Named())
	if !strings.ContainsAny(s.Constructs, `\.`) {
		symbol = engine.LastPart(symbol)
	}

	return s.pattern.MatchString(symbol)
}

// unused says whether nothing refers to the declaration: to a function or type from outside it, to a parameter
// by reading its name. What the language itself calls or binds, a member a supertype dictates, anything else,
// and a declaration the tool cannot name are not judged unused.
func unused(declaration engine.Match) bool {
	switch {
	case declaration.Is(engine.Parameter):
		return declaration.IsUnread()
	case declaration.Is(engine.Function), declaration.Is(engine.TypeDeclaration):
		return declaration.Node().Symbol != "" && !declaration.IsImplicit() && !declaration.IsOverride() && len(declaration.Referrers()) == 0
	}

	return false
}

// passes says whether the argument at the position passes the step; none there passes nothing.
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

// own is the language whose own check the step names, and the check's name; no language for any other step.
func (s Step) own() (contract.Language, string) {
	for language, name := range map[contract.Language]string{contract.PHP: s.PHP, contract.Python: s.Python,
		contract.CSharp: s.CSharp, contract.TypeScript: s.TypeScript, contract.Vue: s.Vue} {
		if name != "" {
			return language, name
		}
	}

	return "", ""
}

// ownChecks checks each language's own check the step, or one nested in it, names: the rule's engine must judge
// that language, and the language must offer the check.
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

// commented says whether a comment on the node, or in the run directly above it, matches the step's pattern.
func (s Step) commented(subject engine.Match) bool {
	for _, comment := range append(subject.Comments(), subject.CommentsAbove()...) {
		if s.pattern.MatchString(comment.Text) {
			return true
		}
	}

	return false
}

// namesAny says whether any of the symbols is the type want names.
func namesAny(symbols []string, want string) bool {
	return slices.ContainsFunc(symbols, func(symbol string) bool { return engine.NamesType(symbol, want) })
}

// typed says whether a written type matches the step's pattern: as it is written, its spaces and the pattern's
// dropped, or as
// the type it names resolves; no type written matches nothing.
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

// typeGlob reads a pattern over types, where `*` is any run of characters and nothing else is special, so `?*`
// is PHP's nullable type.
func typeGlob(pattern string) *regexp.Regexp {
	parts := strings.Split(pattern, "*")
	for i, part := range parts {
		parts[i] = regexp.QuoteMeta(part)
	}

	return regexp.MustCompile("^" + strings.Join(parts, ".*") + "$")
}

// tally counts the nodes below the node that pass the count's step.
func (c Count) tally(match engine.Match) int {
	counted, step := match.Descendants(), c.Descendant

	if c.Child != nil {
		counted, step = match.Children(), c.Child

		if c.Field != "" {
			counted = match.ChildrenIn(c.Field)
		}
	}

	count := 0
	for _, node := range counted {
		if step.check(node) {
			count++
		}
	}

	return count
}

// tally counts the type's members that pass the step, every one when it makes no check.
func (t Tally) tally(match engine.Match) int {
	count := 0
	for _, member := range match.Members() {
		if t.Step.checks() == 0 || t.check(member) {
			count++
		}
	}

	return count
}

// depth counts the node and the nodes above it that pass the step, within its function: a function around it
// ends the count, unless functions are what is counted. An else-if continues its if rather than nesting in it, so
// it adds nothing.
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

// atPosition says whether the node is first, last or the only one among its siblings; an only one is also
// the first and the last.
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

// topLevel says whether the node sits outside every function, closures included, and every type: code that
// runs when its file is loaded.
func topLevel(match engine.Match) bool {
	return !match.Closest(engine.Function).Exists() && !match.EnclosingType().Exists()
}

// nameOf is the name a reader gives the node: its own, or, for a call, the name it calls.
func nameOf(match engine.Match) string {
	if name := match.Name(); name != "" {
		return name
	}

	return match.CalleeName()
}

// bare is a name without the marks a language puts before it: PHP's `$` and the leading underscores of a
// private name.
func bare(name string) string {
	return strings.TrimLeft(name, "$_")
}

// glob is a pattern where `*` stands for any run of characters, separators included, and `?` for one; every
// other character stands for itself, a backslash too, so a PHP name reads as it is written.
func glob(pattern string) *regexp.Regexp {
	var expression strings.Builder
	expression.WriteString("(?s)^")

	for _, character := range pattern {
		switch character {
		case '*':
			expression.WriteString(".*")
		case '?':
			expression.WriteString(".")
		default:
			expression.WriteString(regexp.QuoteMeta(string(character)))
		}
	}

	expression.WriteString("$")

	return regexp.MustCompile(expression.String())
}

// pathGlob reads a pattern over paths as .gitignore does: `*` and `?` stay within one folder, `**` crosses any
// number of them.
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
	case strings.HasPrefix(s.Of, "closest:"):
		return match.Closest(engine.Neutral(strings.TrimPrefix(s.Of, "closest:")))
	case strings.HasPrefix(s.Of, "child:"):
		return match.Child(strings.TrimPrefix(s.Of, "child:"))
	default:
		return match
	}
}

// inFile says whether the file's path, or any tail of it, matches the glob.
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
