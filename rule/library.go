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
	"github.com/jessegall/code-commandments/sins"
)

// The origins a rule file comes from: shipped with the tool, or written by the project under .commandments/custom.
const (
	Installed = "installed"
	Project   = "project"
)

// Source is one rule file a loader reads: where it comes from, its path for a diagnostic, and its text. A rule's name
// is its file's.
type Source struct {
	Origin string
	Path   string
	Text   []byte
}

func (s Source) name() string {
	base := s.Path[strings.LastIndex(s.Path, "/")+1:]

	return strings.TrimSuffix(base, ".json")
}

// Library is every rule the loader read, and a diagnostic for each file it could not, naming the file. A file it
// refuses leaves every other rule available.
type Library struct {
	Rules    []Rule
	Problems []error
}

// Load reads every rule file through one reading: installed and project rules alike. A rule may call a definition of
// another file as [“rule.definition”, …], or qualified as [“installed/rule.definition”, …] or
// [“project/rule.definition”, …] when both origins hold a rule of that name. A sin name or alias written twice is a
// diagnostic naming both files, and the first file read keeps it.
func Load(sources []Source, skills Skills) Library {
	var library Library
	written := map[string][]Source{}
	read := map[string]file{}
	shared := map[string]relation{}
	var rules []Source
	for _, each := range sources {
		if defined, isDefinitions, err := sharedDefinitions(each); isDefinitions {
			if err != nil {
				library.Problems = append(library.Problems, err)
			} else {
				written[each.name()] = append(written[each.name()], each)
				read[each.Path] = file{Definitions: defined}
			}

			continue
		}
		if defined, isRelation, err := sharedRelation(each); isRelation {
			if err != nil {
				library.Problems = append(library.Problems, err)
			} else {
				shared[each.name()] = defined
			}

			continue
		}
		rules = append(rules, each)
	}
	for _, each := range rules {
		decoded, err := decoded(each)
		if err != nil {
			library.Problems = append(library.Problems, err)

			continue
		}
		if decoded.Sin.Name == "" {
			library.Problems = append(library.Problems, fmt.Errorf("%s: a sin needs a name", each.Path))

			continue
		}
		if decoded.IsStub() && each.Origin == Project {
			library.Problems = append(library.Problems, fmt.Errorf("%s: the rule has no find yet; write what it selects", each.Path))

			continue
		}
		written[each.name()] = append(written[each.name()], each)
		if !decoded.IsStub() {
			read[each.Path] = decoded
		}
	}

	claimed := map[string]Source{}
	for _, each := range rules {
		if _, readable := read[each.Path]; !readable {
			continue
		}
		parsed, err := parse(each.name(), each.Text, skills, library.outside(each, written, read), shared)
		if err != nil {
			library.Problems = append(library.Problems, located(each, err))

			continue
		}
		if clash, key := clashing(parsed, claimed); clash != "" {
			library.Problems = append(library.Problems, fmt.Errorf("%s: the sin %q is the rule of %s already; one name reports a site once, so rename one of the two", each.Path, clash, claimed[key].Path))

			continue
		}
		for _, key := range claims(parsed) {
			claimed[key] = each
		}
		parsed.origin = each.Origin
		library.Rules = append(library.Rules, parsed)
	}

	return library
}

// relationFile is a relation many rules read, in a file of its own under rules/relations: rows built once from the
// tree, such as a framework's route table.
type relationFile struct {
	Schema      string                `json:"$schema"`
	Engine      engines               `json:"engine"`
	Definitions map[string]definition `json:"definitions,omitempty"`
	Relation    relation              `json:"relation"`
}

// definitionsFile is definitions many rules call, in a file of its own under rules/definitions: a rule calls one as
// ["file.definition", …].
type definitionsFile struct {
	Schema      string                `json:"$schema"`
	Description string                `json:"description"`
	Definitions map[string]definition `json:"definitions"`
}

// sharedDefinitions reads a definitions file: not one when the file names a sin, a find or a relation, or no
// definitions at all.
func sharedDefinitions(each Source) (map[string]definition, bool, error) {
	var peek map[string]json.RawMessage
	if json.Unmarshal(each.Text, &peek) != nil || peek["definitions"] == nil || peek["sin"] != nil || peek["find"] != nil || peek["relation"] != nil {
		return nil, false, nil
	}
	var read definitionsFile
	decoder := json.NewDecoder(bytes.NewReader(each.Text))
	decoder.DisallowUnknownFields()
	if err := decoder.Decode(&read); err != nil {
		return nil, true, fmt.Errorf("%s: %w", each.Path, err)
	}
	if !isTheFormat(read.Schema) {
		return nil, true, fmt.Errorf("%s: a definitions file is written in %s", each.Path, Format)
	}

	return read.Definitions, true, nil
}

// sharedRelation reads a relation file: not one when the file names no relation.
func sharedRelation(each Source) (relation, bool, error) {
	var peek struct {
		Relation json.RawMessage `json:"relation"`
	}
	if json.Unmarshal(each.Text, &peek) != nil || peek.Relation == nil {
		return relation{}, false, nil
	}
	var read relationFile
	decoder := json.NewDecoder(bytes.NewReader(each.Text))
	decoder.DisallowUnknownFields()
	if err := decoder.Decode(&read); err != nil {
		return relation{}, true, fmt.Errorf("%s: %w", each.Path, err)
	}
	if !isTheFormat(read.Schema) {
		return relation{}, true, fmt.Errorf("%s: a relation file is written in %s", each.Path, Format)
	}
	prefix := each.name() + "."
	brought := read.Relation.mapped(func(expression Expr) Expr { return renamed(expression, prefix, read.Definitions) })
	brought.definitions = map[string]definition{}
	if judged, err := enginesOf(read.Engine); err == nil {
		brought.languages = judgedLanguages(judged)
	}
	for name, defined := range read.Definitions {
		brought.definitions[prefix+name] = definition{defined.Args, renamed(defined.Expr, prefix, read.Definitions), defined.Kept}
	}

	return brought, true, nil
}

func decoded(each Source) (file, error) {
	if err := formatOf(each.name(), each.Text); err != nil {
		return file{}, located(each, err)
	}
	var read file
	if err := strictly(each.Text, &read); err != nil {
		return file{}, fmt.Errorf("%s: %w", each.Path, err)
	}

	return read, nil
}

// located is a parse error named by the file's path from the project rather than by the rule's name.
func located(each Source, err error) error {
	var old *OldFormat
	if errors.As(err, &old) {
		old.File = each.Path

		return old
	}

	return fmt.Errorf("%s: %s", each.Path, strings.TrimPrefix(err.Error(), each.name()+": "))
}

func names(read Rule) []string {
	return append([]string{read.sin.definition.Name}, read.sin.definition.Formerly...)
}

func clashing(read Rule, claimed map[string]Source) (name, key string) {
	for _, key := range claims(read) {
		if _, taken := claimed[key]; taken {
			_, name, _ := strings.Cut(key, "/")
			return name, key
		}
	}

	return "", ""
}

// claims are the names a rule reports under, one per engine it judges: config names a rule engine/name, so two
// rules of different engines may share a former name.
func claims(read Rule) []string {
	var keys []string
	for _, engine := range read.engines {
		for _, name := range names(read) {
			keys = append(keys, string(engine)+"/"+name)
		}
	}

	return keys
}

// outside finds a definition another file declares, for a call written as rule.definition, origin/rule.definition.
func (l Library) outside(asking Source, written map[string][]Source, read map[string]file) outsideDefinitions {
	return func(call string) (map[string]definition, error) {
		origin, qualified, isQualified := strings.Cut(call, "/")
		if !isQualified {
			origin, qualified = "", call
		}
		ruleName, defined, isCall := strings.Cut(qualified, ".")
		if !isCall {
			return nil, nil
		}
		var candidates []Source
		for _, each := range written[ruleName] {
			if origin == "" || each.Origin == origin {
				candidates = append(candidates, each)
			}
		}
		switch {
		case len(candidates) == 0:
			return nil, fmt.Errorf("no rule %q holds the definition %q", ruleName, call)
		case len(candidates) > 1:
			var paths []string
			for _, each := range candidates {
				paths = append(paths, each.Path)
			}

			return nil, fmt.Errorf("%q is ambiguous: %s both hold a rule %q; write installed/%s or project/%s", call, strings.Join(paths, " and "), ruleName, qualified, qualified)
		}
		holder := read[candidates[0].Path]
		if _, found := holder.Definitions[defined]; !found {
			return nil, fmt.Errorf("the rule %s holds no definition %q%s", candidates[0].Path, defined, didYouMean(defined, slices.Collect(maps.Keys(holder.Definitions))))
		}

		return imported(call, defined, holder.Definitions), nil
	}
}

// imported is a definition of another file under the name it is called by, with every definition of that file it
// calls in turn, renamed the same way so none collides with the caller's own.
func imported(call, defined string, own map[string]definition) map[string]definition {
	prefix := strings.TrimSuffix(call, defined)
	taken := map[string]definition{}
	var bring func(name string)
	bring = func(name string) {
		if _, done := taken[prefix+name]; done {
			return
		}
		body := own[name]
		taken[prefix+name] = definition{body.Args, renamed(body.Expr, prefix, own), body.Kept}
		body.Expr.Each(func(each Expr) {
			if _, local := own[each.Operator]; local {
				bring(each.Operator)
			}
		})
	}
	bring(defined)

	return taken
}

func renamed(expression Expr, prefix string, own map[string]definition) Expr {
	copied := expression
	if _, local := own[expression.Operator]; local {
		copied.Operator = prefix + expression.Operator
	}
	copied.Operands = make([]Expr, len(expression.Operands))
	for at, operand := range expression.Operands {
		copied.Operands[at] = renamed(operand, prefix, own)
	}
	if expression.Arms != nil {
		copied.Arms = map[string]Expr{}
		for value, arm := range expression.Arms {
			copied.Arms[value] = renamed(arm, prefix, own)
		}
	}

	return copied
}

// Described is what a rule file says of itself, whether its find is written yet or not: the sin it names and the
// engines it judges.
type Described struct {
	Sin         sins.Sin
	Engines     []catalog.Engine
	Unpublished bool
}

// Describe reads the metadata block of every rule file, a stub's too, for the lists, documents and commands that
// name rules; a file whose block cannot be read is left out, and Load reports it.
func Describe(sources []Source, skills Skills) []Described {
	var described []Described
	for _, each := range sources {
		read, err := decoded(each)
		if err != nil {
			continue
		}
		judges, err := enginesOf(read.Engine)
		taught, found := skills(read.Sin.Skill)
		if err != nil || !found {
			continue
		}
		described = append(described, Described{read.Sin.sin(taught), judges, read.Sin.Unpublished})
	}

	return described
}
