// Package rule is a detector a project writes as data: the engine it judges, the sin it finds, and the query
// that finds it, composed from the same selectors and checks a shipped detector composes in code.
package rule

import (
	"bytes"
	"encoding/json"
	"fmt"
	"path"
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

// Step is one check of a rule's query, on the node itself or, with Of or Descendant, on one related to it.
type Step struct {
	Is            string   `json:"is,omitempty"`
	Kind          string   `json:"kind,omitempty"`
	Name          string   `json:"name,omitempty"`
	NameIn        []string `json:"nameIn,omitempty"`
	NameLike      string   `json:"nameLike,omitempty"`
	NameMatches   string   `json:"nameMatches,omitempty"`
	NameCase      string   `json:"nameCase,omitempty"`
	Text          *string  `json:"text,omitempty"`
	TextLike      string   `json:"textLike,omitempty"`
	TextMatches   string   `json:"textMatches,omitempty"`
	Resolves      string   `json:"resolves,omitempty"`
	ResolvesLike  string   `json:"resolvesLike,omitempty"`
	NamespaceLike string   `json:"namespaceLike,omitempty"`
	Layer         string   `json:"layer,omitempty"`
	HasModifier   string   `json:"hasModifier,omitempty"`
	HasFlag       string   `json:"hasFlag,omitempty"`
	WithinLoop    *bool    `json:"withinLoop,omitempty"`
	Documented    *bool    `json:"documented,omitempty"`
	File          string   `json:"file,omitempty"`
	Of            string   `json:"of,omitempty"`
	Descendant    *Step    `json:"descendant,omitempty"`

	// pattern is the step's glob or regular expression, compiled once when the rule is read.
	pattern *regexp.Regexp

	// layers are the layers the project declares for the rule's engine, which a layer step looks its name up in.
	layers *engine.LayerStack
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
		return Rule{}, fmt.Errorf("%s: %w", name, err)
	}

	engine := catalog.Engine(read.Engine)
	if _, known := languages[engine]; !known {
		return Rule{}, fmt.Errorf("%s: engine %q is none of backend, frontend, typescript, python, csharp", name, read.Engine)
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
		step.layers = stack
		if step.Descendant != nil {
			step.Descendant = &withLayers([]Step{*step.Descendant}, stack)[0]
		}

		copied[i] = step
	}

	return copied
}

// usesLayers says whether the step, or one nested in it, is a layer step.
func (s Step) usesLayers() bool {
	return s.Layer != "" || (s.Descendant != nil && s.Descendant.usesLayers())
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
		return nil, fmt.Errorf("select %q is neither a neutral kind (%s) nor kind:<Kind>", named, neutralList())
	}

	return func(c *engine.Codebase) *engine.Query { return c.WhereIs(engine.Neutral(named)) }, nil
}

func neutralList() string {
	var names []string
	for _, neutral := range engine.Neutrals {
		names = append(names, string(neutral))
	}

	return strings.Join(names, ", ")
}

// checks counts the checks a step makes; a step makes exactly one.
func (s Step) checks() int {
	count := 0

	for _, set := range []bool{s.Is != "", s.Kind != "", s.Name != "", s.NameIn != nil, s.NameLike != "",
		s.NameMatches != "", s.NameCase != "", s.Text != nil, s.TextLike != "", s.TextMatches != "", s.Resolves != "",
		s.ResolvesLike != "", s.NamespaceLike != "", s.Layer != "", s.HasModifier != "", s.HasFlag != "", s.WithinLoop != nil,
		s.Documented != nil, s.File != "", s.Descendant != nil} {
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
	case s.NameMatches != "":
		return s.compile(s.NameMatches)
	case s.TextMatches != "":
		return s.compile(s.TextMatches)
	case s.Descendant != nil:
		return s.Descendant.prepare()
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
		return fmt.Errorf("is %q is no neutral kind: %s", s.Is, neutralList())
	}

	if s.NameCase != "" && cases[s.NameCase] == nil {
		return fmt.Errorf("nameCase %q is none of camel, pascal, snake, upper, kebab", s.NameCase)
	}

	if s.Of != "" && s.Of != "parent" && s.Of != "enclosingFunction" && s.Of != "enclosingType" && !strings.HasPrefix(s.Of, "child:") {
		return fmt.Errorf("of %q is none of parent, enclosingFunction, enclosingType, child:<field>", s.Of)
	}

	return nil
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
		return s.pattern.MatchString(nameOf(subject))
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
		return s.pattern.MatchString(subject.Namespace())
	case s.Layer != "":
		return s.layers != nil && s.layers.LayerOf(subject.Namespace()) == strings.Trim(s.Layer, `\`)
	case s.HasModifier != "":
		return subject.HasModifier(s.HasModifier)
	case s.HasFlag != "":
		return subject.HasFlag(s.HasFlag)
	case s.WithinLoop != nil:
		return subject.IsWithinLoop() == *s.WithinLoop
	case s.Documented != nil:
		return subject.IsDocumented() == *s.Documented
	case s.File != "":
		return inFile(s.File, subject.File())
	case s.Descendant != nil:
		return slices.ContainsFunc(subject.Descendants(), s.Descendant.check)
	}

	return false
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
	expression.WriteString("^")

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

func (s Step) subject(match engine.Match) engine.Match {
	switch {
	case s.Of == "parent":
		return match.Parent()
	case s.Of == "enclosingFunction":
		return match.EnclosingFunction()
	case s.Of == "enclosingType":
		return match.EnclosingType()
	case strings.HasPrefix(s.Of, "child:"):
		return match.Child(strings.TrimPrefix(s.Of, "child:"))
	default:
		return match
	}
}

// inFile says whether the file's path, or any tail of it, matches the glob.
func inFile(glob, file string) bool {
	segments := strings.Split(strings.ReplaceAll(file, `\`, "/"), "/")

	for i := range segments {
		if matched, _ := path.Match(glob, strings.Join(segments[i:], "/")); matched {
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
