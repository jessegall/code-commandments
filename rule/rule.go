// Package rule is a detector a project writes as data: the engine it judges, the sin it finds, and the query
// that finds it, composed from the same selectors and checks a shipped detector composes in code.
package rule

import (
	"bytes"
	"encoding/json"
	"fmt"
	"path"
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
	Is          string   `json:"is,omitempty"`
	Kind        string   `json:"kind,omitempty"`
	Name        string   `json:"name,omitempty"`
	NameIn      []string `json:"nameIn,omitempty"`
	Text        *string  `json:"text,omitempty"`
	Resolves    string   `json:"resolves,omitempty"`
	HasModifier string   `json:"hasModifier,omitempty"`
	HasFlag     string   `json:"hasFlag,omitempty"`
	WithinLoop  *bool    `json:"withinLoop,omitempty"`
	Documented  *bool    `json:"documented,omitempty"`
	File        string   `json:"file,omitempty"`
	Of          string   `json:"of,omitempty"`
	Descendant  *Step    `json:"descendant,omitempty"`
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

	for _, step := range append(append([]Step(nil), read.Find.Where...), read.Find.Reject...) {
		if err := step.valid(); err != nil {
			return Rule{}, fmt.Errorf("%s: %w", name, err)
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

	for _, set := range []bool{s.Is != "", s.Kind != "", s.Name != "", s.NameIn != nil, s.Text != nil, s.Resolves != "",
		s.HasModifier != "", s.HasFlag != "", s.WithinLoop != nil, s.Documented != nil, s.File != "", s.Descendant != nil} {
		if set {
			count++
		}
	}

	return count
}

func (s Step) valid() error {
	if s.checks() != 1 {
		return fmt.Errorf("a step makes one check, and %s makes %d", s.describe(), s.checks())
	}

	if s.Is != "" && !slices.Contains(engine.Neutrals, engine.Neutral(s.Is)) {
		return fmt.Errorf("is %q is no neutral kind: %s", s.Is, neutralList())
	}

	if s.Of != "" && s.Of != "parent" && s.Of != "enclosingFunction" && s.Of != "enclosingType" && !strings.HasPrefix(s.Of, "child:") {
		return fmt.Errorf("of %q is none of parent, enclosingFunction, enclosingType, child:<field>", s.Of)
	}

	if s.Descendant != nil {
		return s.Descendant.valid()
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
	case s.Text != nil:
		text, isText := subject.Text()

		return isText && text == *s.Text
	case s.Resolves != "":
		return strings.TrimPrefix(subject.Resolves(), `\`) == strings.TrimPrefix(s.Resolves, `\`)
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

// nameOf is the name a reader gives the node: its own, or, for a call or an access, the one its name child
// carries.
func nameOf(match engine.Match) string {
	if name := match.Name(); name != "" {
		return name
	}

	return match.Child("name").Name()
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
