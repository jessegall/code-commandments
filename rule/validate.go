package rule

import (
	"cmp"
	"fmt"
	"maps"
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// What a reference may read past a node's roles, links and facts: the node's own properties, then a file's, a span's
// and a resolved type's.
var (
	nodeProperties = []string{"kind", "role", "file", "span", "line", "parent", "resolved", "target"}
	fileProperties = []string{"path", "language", "module", "test", "purpose", "errors", "publishedBy", "frozen"}
	spanProperties = []string{"start", "end", "line", "endLine", "column", "endColumn"}
	typeProperties = []string{"text", "kind", "name", "args", "members", "fields", "parameters", "returns", "value", "nullable", "valueType", "element", "constructs", "origin"}
)

var plainName = regexp.MustCompile(`^[A-Za-z_][A-Za-z0-9_]*$`)

// scope is what an expression may refer to: each bound name with the kinds it can be (none when only the tree knows),
// the names bound further on, which a binding never reads, and the names a group holds in its rows until they are
// expanded.
type scope struct {
	bound   map[string][]string
	later   []string
	grouped []string
}

func (s scope) with(name string, kinds []string) scope {
	bound := maps.Clone(s.bound)
	bound[name] = kinds

	return scope{bound, s.later, s.grouped}
}

// checker validates one rule file before anything of it runs. Over rows, a name may hold a record whose fields the
// rule itself names, so only the names are checked, never what is read through them.
type checker struct {
	definitions map[string]definition
	parameters  map[string]parameter
	overRows    bool
	relations   map[string]relation
}

// parameterName is the name every expression reads a rule's parameters through, as $param.<name>.
const parameterName = "param"

// validate checks the rule's definitions and its find, naming the step and the field of each fault it finds.
func validate(read file) error {
	c := checker{definitions: read.Definitions, parameters: read.Parameters, overRows: read.Find.From != nil, relations: read.Find.Relations}
	for _, name := range slices.Sorted(maps.Keys(read.Parameters)) {
		declared := read.Parameters[name]
		holds, typed := parameterTypes[declared.Type]
		switch {
		case !typed:
			return fmt.Errorf("parameters.%s: the type %q is none of number, string, list and map", name, declared.Type)
		case !holds(declared.Default):
			return fmt.Errorf("parameters.%s: the default %v is no %s", name, declared.Default, declared.Type)
		}
	}
	for _, name := range slices.Sorted(maps.Keys(read.Definitions)) {
		if err := c.definition(name); err != nil {
			return err
		}
	}
	if err := c.cycles(); err != nil {
		return err
	}

	return c.find(read.Find)
}

func (c checker) definition(name string) error {
	defined := c.definitions[name]
	if _, taken := operators[name]; taken {
		return fmt.Errorf("definitions.%s: %q is an operator of the rule language, so a definition cannot take its name", name, name)
	}
	bound := scope{bound: map[string][]string{}}
	for _, arg := range defined.Args {
		if !plainName.MatchString(arg) {
			return fmt.Errorf("definitions.%s: the argument %q is not a plain name", name, arg)
		}
		bound = bound.with(arg, nil)
	}
	_, err := c.expr(defined.Expr, "definitions."+name, bound)

	return err
}

// cycles refuses definitions that call each other round, which no evaluation could finish.
func (c checker) cycles() error {
	calls := map[string][]string{}
	for name, defined := range c.definitions {
		defined.Expr.Each(func(each Expr) {
			if _, isDefinition := c.definitions[each.Operator]; isDefinition && !slices.Contains(calls[name], each.Operator) {
				calls[name] = append(calls[name], each.Operator)
			}
		})
	}
	state := map[string]int{}
	var path []string
	var visit func(name string) error
	visit = func(name string) error {
		switch state[name] {
		case 1:
			at := slices.Index(path, name)

			return fmt.Errorf("definitions: %s call each other in a cycle", strings.Join(append(path[at:], name), " → "))
		case 2:
			return nil
		}
		state[name] = 1
		path = append(path, name)
		for _, called := range slices.Sorted(slices.Values(calls[name])) {
			if err := visit(called); err != nil {
				return err
			}
		}
		path = path[:len(path)-1]
		state[name] = 2

		return nil
	}
	for _, name := range slices.Sorted(maps.Keys(c.definitions)) {
		if err := visit(name); err != nil {
			return err
		}
	}

	return nil
}

func (c checker) find(written find) error {
	if (len(written.Select) == 0) == (written.From == nil) {
		return fmt.Errorf("find: a rule selects the nodes it judges, or reads the rows of a relation from, naming one of select and from")
	}
	if written.From != nil {
		return c.rows(written)
	}
	kinds, err := selected(written.Select)
	if err != nil {
		return err
	}
	if !plainName.MatchString(written.As) {
		return fmt.Errorf("find.as: a selection is bound to a plain name, such as \"site\", which the rest of find reads as $site")
	}

	var later []string
	for _, each := range written.Let {
		later = append(later, each.Name)
	}
	bound := scope{bound: map[string][]string{written.As: kinds}, later: later}
	if written.Where != nil {
		if err := c.truth(*written.Where, "find.where", bound); err != nil {
			return err
		}
	}
	for at, each := range written.Let {
		where := fmt.Sprintf("find.let[%d] (%s)", at, each.Name)
		if !plainName.MatchString(each.Name) {
			return fmt.Errorf("%s: a binding's name is a plain name", where)
		}
		if _, taken := bound.bound[each.Name]; taken {
			return fmt.Errorf("%s: $%s is bound already", where, each.Name)
		}
		bound.later = bound.later[1:]
		if _, err := c.expr(each.Expr, where, bound); err != nil {
			return err
		}
		bound = bound.with(each.Name, nil)
	}
	if written.Having != nil {
		if err := c.truth(*written.Having, "find.having", bound); err != nil {
			return err
		}
	}
	if written.Emit != nil {
		if _, err := c.expr(*written.Emit, "find.emit", bound); err != nil {
			return err
		}
	}
	if written.Advice != nil {
		if _, err := c.expr(*written.Advice, "find.advice", scope{bound: map[string][]string{written.As: kinds}}); err != nil {
			return err
		}
	}

	return nil
}

// selected is the kinds a selection names: each a kind of the contract, or a category of them.
func selected(names []string) ([]string, error) {
	var kinds []string
	for _, name := range names {
		named := contract.Category(name)
		if _, isKind := contract.Kind(name); isKind || name == commentSource {
			named = []string{name}
		}
		if len(named) == 0 {
			return nil, fmt.Errorf("find.select: %q is no kind or category of the tree%s", name, didYouMean(name, contract.Kinds()))
		}
		for _, kind := range named {
			if !slices.Contains(kinds, kind) {
				kinds = append(kinds, kind)
			}
		}
	}

	return kinds, nil
}

func (c checker) truth(e Expr, at string, bound scope) error {
	gives, err := c.expr(e, at, bound)
	if err == nil && gives != truth && gives != anyValue {
		return fmt.Errorf("%s: %s gives a %s, where a truth is asked for", at, e.Written(), gives)
	}

	return err
}

// expr checks one expression and says what it gives.
func (c checker) expr(e Expr, at string, bound scope) (valueType, error) {
	switch {
	case e.IsReference():
		return c.reference(e, at, bound)
	case e.IsArms():
		return "", fmt.Errorf("%s: arms {…} stand only as the second operand of a case", at)
	case e.IsCall():
		return c.call(e, at, bound)
	}

	return literalType(e.Value), nil
}

func literalType(value any) valueType {
	switch value.(type) {
	case string:
		return text
	case float64:
		return number
	case bool:
		return truth
	}

	return anyValue
}

func (c checker) reference(e Expr, at string, bound scope) (valueType, error) {
	if _, shadowed := bound.bound[parameterName]; e.Name() == parameterName && !shadowed {
		return c.parameter(e, at)
	}
	kinds, isBound := bound.bound[e.Name()]
	if !isBound {
		if slices.Contains(bound.later, e.Name()) {
			return "", fmt.Errorf("%s: $%s is bound further on: a binding reads only the names bound before it", at, e.Name())
		}
		if slices.Contains(bound.grouped, e.Name()) {
			return "", fmt.Errorf("%s: $%s is held in each group's rows: read it as $rows.%s, or after an expand", at, e.Name(), e.Name())
		}

		return "", fmt.Errorf("%s: $%s is not bound here%s", at, e.Name(), boundNames(bound))
	}
	if c.overRows {
		return anyValue, nil
	}
	if err := readable(e.Reference, kinds, at); err != nil {
		return "", err
	}
	if len(e.Reference) == 1 && len(kinds) > 0 {
		return node, nil
	}

	return anyValue, nil
}

func boundNames(bound scope) string {
	names := slices.Sorted(maps.Keys(bound.bound))
	if len(names) == 0 {
		return ", and nothing is"
	}

	return ", where $" + strings.Join(names, ", $") + " are"
}

// readable checks each step of a reference: the first against the kinds its name can be, when known, and each later one
// against what any node, file, span or type can hold.
func readable(reference []string, kinds []string, at string) error {
	within := "node"
	for step, segment := range reference[1:] {
		written := "$" + strings.Join(reference[:step+2], ".")
		switch within {
		case "file":
			if !slices.Contains(fileProperties, segment) {
				return fmt.Errorf("%s: %s: a file has no %q, only %s", at, written, segment, strings.Join(fileProperties, ", "))
			}
			within = "value"
		case "span":
			if !slices.Contains(spanProperties, segment) {
				return fmt.Errorf("%s: %s: a span has no %q, only %s", at, written, segment, strings.Join(spanProperties, ", "))
			}
			within = "value"
		case "type":
			if !slices.Contains(typeProperties, segment) {
				return fmt.Errorf("%s: %s: a resolved type has no %q%s", at, written, segment, didYouMean(segment, typeProperties))
			}
		case "value":
			return fmt.Errorf("%s: %s: %q is read past a plain value", at, written, segment)
		default:
			possible := kinds
			if step > 0 {
				possible = nil
			}
			if len(possible) == 0 && slices.Contains(typeProperties, segment) && holds(segment, nil, written, at) != nil {
				// a name whose kind is unknown may hold a resolved type as well as a node
				within = "type"
				if segment != "element" && segment != "returns" {
					within = "value"
				}
				continue
			}
			if err := holds(segment, possible, written, at); err != nil {
				return err
			}
			within = map[string]string{"file": "file", "span": "span", "resolved": "type", "kind": "value", "role": "value", "line": "value"}[segment]
			if within == "" {
				within = "node"
			}
		}
	}

	return nil
}

// holds checks that some kind a node can be has the role, link or fact a reference reads; any kind of the tree when
// only the tree knows the node's kind.
func holds(segment string, kinds []string, written, at string) error {
	if slices.Contains(nodeProperties, segment) || slices.Contains(kinds, commentSource) {
		return nil
	}
	candidates := kinds
	if len(candidates) == 0 {
		candidates = contract.Kinds()
	}
	for _, kind := range candidates {
		if _, declared := contract.DeclaredRole(kind, segment); declared {
			return nil
		}
		if _, declared := contract.DeclaredLink(kind, segment); declared {
			return nil
		}
		if contract.FactApplies(kind, segment) {
			return nil
		}
	}
	if len(kinds) > 0 {
		return fmt.Errorf("%s: %s: no kind it can be (%s) has a role, link or fact called %q", at, written, strings.Join(kinds, ", "), segment)
	}

	return fmt.Errorf("%s: %s: no kind of the tree has a role, link or fact called %q", at, written, segment)
}

func (c checker) call(e Expr, at string, bound scope) (valueType, error) {
	if defined, isDefinition := c.definitions[e.Operator]; isDefinition {
		if len(e.Operands) != len(defined.Args) {
			return "", fmt.Errorf("%s: the definition %q takes %d operands (%s), and %d are written", at, e.Operator, len(defined.Args), strings.Join(defined.Args, ", "), len(e.Operands))
		}
		for _, operand := range e.Operands {
			if _, err := c.expr(operand, at, bound); err != nil {
				return "", err
			}
		}

		return anyValue, nil
	}
	spec, known := operators[e.Operator]
	if !known {
		return "", fmt.Errorf("%s: no operator or definition is called %q%s", at, e.Operator, didYouMean(e.Operator, append(operatorNames(), slices.Collect(maps.Keys(c.definitions))...)))
	}
	if !spec.takes(len(e.Operands)) {
		return "", fmt.Errorf("%s: %q takes %s, and %d are written", at, e.Operator, spec.arity(), len(e.Operands))
	}
	if named, readsRows := e.rowsNamed(); readsRows {
		_, isRelation := c.relations[named]
		if _, isProgram := programRelations[named]; !isRelation && !isProgram {
			return "", fmt.Errorf("%s: %q reads the rows of %q, which is no relation of the find and none of the program relations %v", at, e.Operator, named, programRelationNames())
		}
	}
	if pattern, isText := e.patternWritten(); isText {
		if _, err := compiledPattern(pattern); err != nil {
			return "", fmt.Errorf("%s: the pattern %q of matchesText does not compile: %v", at, pattern, err)
		}
	}
	inner := bound
	for position, operand := range e.Operands {
		wanted := spec.operand(position)
		where := fmt.Sprintf("%s, operand %d of %q", at, position+1, e.Operator)
		switch wanted {
		case iteratorName:
			name, isName := operand.Value.(string)
			if !isName || !plainName.MatchString(name) {
				return "", fmt.Errorf("%s: %q binds a plain name here, such as \"s\", which its last operand reads as $s", where, e.Operator)
			}
			inner = bound.with(name, nil)

			continue
		case arms:
			if !operand.IsArms() {
				return "", fmt.Errorf("%s: a case's second operand is its arms, an object from a value to what answers for it", where)
			}
			if e.Operator == "matchesShape" {
				continue
			}
			for _, value := range slices.Sorted(maps.Keys(operand.Arms)) {
				if _, err := c.expr(operand.Arms[value], fmt.Sprintf("%s, arm %q", where, value), armScope(inner, e.Operands[0], value)); err != nil {
					return "", err
				}
			}

			continue
		}
		gives, err := c.expr(operand, where, inner)
		if err != nil {
			return "", err
		}
		if wanted != anyValue && gives != anyValue && gives != wanted {
			return "", fmt.Errorf("%s: %s is a %s, where %q takes a %s", where, operand.Written(), gives, e.Operator, wanted)
		}
	}

	return spec.gives, nil
}

// rows checks a find that reads rows: every source it names, every relation it builds, and each stage's expressions
// against the names bound before it.
func (c checker) rows(written find) error {
	if len(written.Pipeline) == 0 {
		return fmt.Errorf("find.pipeline: a find that reads from %q names the pipeline its rows go through", written.From)
	}
	if !plainName.MatchString(written.As) {
		return fmt.Errorf("find.as: the rows are bound to a plain name, such as \"f\", which the pipeline reads as $f")
	}
	for _, name := range slices.Sorted(maps.Keys(written.Relations)) {
		defined := written.Relations[name]
		at := "find.relations." + name
		if err := c.sources(defined.From, written.Relations, at); err != nil {
			return err
		}
		_, err := c.stages(defined.Pipeline, scope{bound: map[string][]string{cmp.Or(defined.As, "item"): nil}}, at)
		if err != nil {
			return err
		}
		for _, aggregate := range slices.Sorted(maps.Keys(defined.Aggregate)) {
			if _, err := c.expr(defined.Aggregate[aggregate], at+".aggregate."+aggregate, scope{bound: map[string][]string{"rows": nil}}); err != nil {
				return err
			}
		}
	}
	if err := c.sources(written.From, written.Relations, "find.from"); err != nil {
		return err
	}
	if cycle := relationCycle(written.Relations); len(cycle) > 0 {
		return fmt.Errorf("find.relations: %s reads itself, which never ends building; a relation reads only relations that do not read it back", strings.Join(cycle, " reads "))
	}
	if _, err := c.stages(written.Pipeline, scope{bound: map[string][]string{written.As: nil}}, "find.pipeline"); err != nil {
		return err
	}
	if !slices.ContainsFunc(written.Pipeline, func(each stage) bool { return each.Emit != nil }) {
		return fmt.Errorf("find.pipeline: no stage emits the node a finding reports at")
	}

	return nil
}

// relationCycle is a chain of relations that comes back to where it started, through what each reads from or joins,
// or none when every relation reads only relations that do not read it back.
func relationCycle(relations map[string]relation) []string {
	reads := func(name string) []string {
		var read []string
		for _, each := range relations[name].From {
			if _, isRelation := relations[each]; isRelation {
				read = append(read, each)
			}
		}
		for _, step := range relations[name].Pipeline {
			if _, isRelation := relations[step.Join]; isRelation {
				read = append(read, step.Join)
			}
		}

		return read
	}
	finished := map[string]bool{}
	var walk func(chain []string) []string
	walk = func(chain []string) []string {
		name := chain[len(chain)-1]
		if at := slices.Index(chain[:len(chain)-1], name); at >= 0 {
			return chain[at:]
		}
		if finished[name] {
			return nil
		}
		for _, next := range reads(name) {
			if cycle := walk(append(slices.Clone(chain), next)); len(cycle) > 0 {
				return cycle
			}
		}
		finished[name] = true

		return nil
	}
	for _, name := range slices.Sorted(maps.Keys(relations)) {
		if cycle := walk([]string{name}); len(cycle) > 0 {
			return cycle
		}
	}

	return nil
}

func (c checker) sources(names source, relations map[string]relation, at string) error {
	for _, name := range names {
		if _, isProgram := programRelations[name]; isProgram {
			continue
		}
		if _, isRelation := relations[name]; isRelation {
			continue
		}
		if _, err := selected([]string{name}); err != nil {
			return fmt.Errorf("%s: %q is no relation of the rule, no kind or category of the tree, and none of the program relations %v", at, name, programRelationNames())
		}
	}

	return nil
}

// stages checks each stage against the names bound before it, and says what is bound after the last.
func (c checker) stages(stages []stage, bound scope, at string) (scope, error) {
	for position, step := range stages {
		where := fmt.Sprintf("%s[%d]", at, position)
		for _, truthful := range []*Expr{step.Reject, step.Where, step.Having} {
			if truthful != nil {
				if err := c.truth(*truthful, where, bound); err != nil {
					return bound, err
				}
			}
		}
		checked := slices.Concat(step.OrderBy, step.FirstBy)
		for _, written := range []*Expr{step.GroupBy, step.Expand, step.Emit, step.Group, step.Distinct} {
			if written != nil {
				checked = append(checked, *written)
			}
		}
		for _, field := range slices.Sorted(maps.Keys(step.On)) {
			checked = append(checked, step.On[field])
		}
		for _, expression := range checked {
			if _, err := c.expr(expression, where, bound); err != nil {
				return bound, err
			}
		}
		projected := bound
		for _, name := range slices.Sorted(maps.Keys(step.Project)) {
			if _, err := c.expr(step.Project[name], where+".project."+name, bound); err != nil {
				return bound, err
			}
			projected = projected.with(name, nil)
		}
		bound = projected
		switch {
		case step.Join != "":
			if !slices.Contains([]string{"", "first", "inner", "semi", "anti", "all"}, step.Mode) {
				return bound, fmt.Errorf("%s: a join's mode is first, inner, semi, anti or all, and %q is none of them", where, step.Mode)
			}
			if step.Mode == "semi" || step.Mode == "anti" {
				break
			}
			if !plainName.MatchString(step.As) {
				return bound, fmt.Errorf("%s: a join binds the record it finds to a plain name, written as", where)
			}
			bound = bound.with(step.As, nil)
		case step.GroupBy != nil:
			name := "key"
			if step.GroupBy.IsReference() && len(step.GroupBy.Reference) == 1 {
				name = step.GroupBy.Name()
			}
			bound = scope{bound: map[string][]string{name: nil, "rows": nil}, grouped: slices.Collect(maps.Keys(projected.bound))}
		case step.Expand != nil:
			bound = bound.with("row", nil)
			for _, name := range bound.grouped {
				bound = bound.with(name, nil)
			}
			bound.grouped = nil
		}
	}

	return bound, nil
}

// parameter checks a reference to a declared parameter, and says what its type gives.
func (c checker) parameter(e Expr, at string) (valueType, error) {
	if len(e.Reference) < 2 {
		return "", fmt.Errorf("%s: $param is read by the parameter's name, as $param.<name>", at)
	}
	declared, found := c.parameters[e.Reference[1]]
	if !found {
		return "", fmt.Errorf("%s: %s reads a parameter the rule does not declare%s", at, e.Written(), didYouMean(e.Reference[1], slices.Collect(maps.Keys(c.parameters))))
	}
	if len(e.Reference) > 2 {
		return anyValue, nil
	}

	return map[string]valueType{"number": number, "string": text, "list": list, "map": anyValue}[declared.Type], nil
}

// armScope is the scope an arm of a case over $x.kind is read in: there $x is a node of that arm's kind.
func armScope(bound scope, subject Expr, value string) scope {
	reference := subject.Reference
	if len(reference) != 2 || reference[1] != "kind" || !slices.Contains(contract.Kinds(), value) {
		return bound
	}
	if _, isBound := bound.bound[reference[0]]; !isBound {
		return bound
	}

	return bound.with(reference[0], []string{value})
}
