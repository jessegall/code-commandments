package rule

import (
	"fmt"
	"maps"
	"slices"
	"strings"
)

// valueType is what an expression gives: a truth, a number, a list, a text, a node, or anything when it can only be
// known on the tree.
type valueType string

const (
	anyValue valueType = "value"
	truth    valueType = "truth"
	number   valueType = "number"
	list     valueType = "list"
	text     valueType = "text"
	node     valueType = "node"
	// iteratorName is a plain name an operator binds for its last operand, such as the "s" of ["filter", "$xs", "s", …].
	iteratorName valueType = "name"
	// arms is a case's arms, written as an object from a value to the expression that answers for it.
	arms valueType = "arms"
)

// operator is one operator of the rule language: the operands it takes in order (the last repeats when repeating is
// set), how many of them may be left out at the end, the type it gives, and what it says.
type operator struct {
	operands  []valueType
	repeating bool
	optional  int
	gives     valueType
	says      string
}

// computation is an operator whose operands are all asked before it answers, given in order.
type computation func(e evaluation, operands []value) value

// computed are the operators evaluated from their asked operands, each file of operators adding its own.
var computed = map[string]computation{}

// define adds a file's operators to the language: what each takes and says, and how it is answered.
func define(described map[string]operator, answered map[string]computation) {
	for name, spec := range described {
		if _, taken := operators[name]; taken {
			panic("the rule language defines the operator " + name + " twice")
		}
		operators[name] = spec
	}
	maps.Copy(computed, answered)
}

func (o operator) takes(count int) bool {
	if o.repeating {
		return count >= len(o.operands)-o.optional
	}

	return count >= len(o.operands)-o.optional && count <= len(o.operands)
}

func (o operator) operand(at int) valueType {
	if at >= len(o.operands) {
		return o.operands[len(o.operands)-1]
	}

	return o.operands[at]
}

func (o operator) arity() string {
	switch {
	case o.repeating:
		return fmt.Sprintf("%d or more operands", len(o.operands)-o.optional)
	case o.optional > 0:
		return fmt.Sprintf("%d to %d operands", len(o.operands)-o.optional, len(o.operands))
	case len(o.operands) == 1:
		return "1 operand"
	}

	return fmt.Sprintf("%d operands", len(o.operands))
}

// operators is the whole rule language: every operator a rule may apply, and none that runs code of the tool's own.
var operators = map[string]operator{
	"and":            {operands: []valueType{truth, truth}, repeating: true, gives: truth, says: "true when every operand is true; false when one is false; unknown otherwise"},
	"or":             {operands: []valueType{truth, truth}, repeating: true, gives: truth, says: "true when one operand is true; false when every operand is false; unknown otherwise"},
	"not":            {operands: []valueType{truth}, gives: truth, says: "the operand's truth turned over; unknown stays unknown"},
	"=":              {operands: []valueType{anyValue, anyValue}, gives: truth, says: "whether two values are equal; unknown when either is unresolved"},
	"!=":             {operands: []valueType{anyValue, anyValue}, gives: truth, says: "whether two values differ; unknown when either is unresolved"},
	"<":              {operands: []valueType{number, number}, gives: truth, says: "whether the first number is less than the second"},
	"<=":             {operands: []valueType{number, number}, gives: truth, says: "whether the first number is at most the second"},
	">":              {operands: []valueType{number, number}, gives: truth, says: "whether the first number is greater than the second"},
	">=":             {operands: []valueType{number, number}, gives: truth, says: "whether the first number is at least the second"},
	"count":          {operands: []valueType{list}, gives: number, says: "how many items a list holds; unknown when the list is"},
	"filter":         {operands: []valueType{list, iteratorName, truth}, gives: list, says: "the items for which the body, with the name bound to each, is true"},
	"map":            {operands: []valueType{list, iteratorName, anyValue}, gives: list, says: "the body's value for each item, with the name bound to it"},
	"some":           {operands: []valueType{list, iteratorName, truth}, gives: truth, says: "whether the body is true for at least one item"},
	"every":          {operands: []valueType{list, iteratorName, truth}, gives: truth, says: "whether the body is true for every item"},
	"none":           {operands: []valueType{list, iteratorName, truth}, gives: truth, says: "whether the body is true for no item"},
	"case":           {operands: []valueType{anyValue, arms, anyValue}, optional: 1, gives: anyValue, says: "the arm the value names, evaluated alone; the last operand when no arm names it"},
	"exists":         {operands: []valueType{anyValue}, gives: truth, says: "whether a role, link or fact holds something"},
	"nonempty":       {operands: []valueType{anyValue}, gives: truth, says: "whether a value holds something: a non-empty text, list or node"},
	"literalIs":      {operands: []valueType{anyValue, text}, gives: truth, says: "whether a node is a literal of the kind named: null, undefined, boolean, integer, float, string"},
	"literalEquals":  {operands: []valueType{anyValue, anyValue}, gives: truth, says: "whether a node is a literal whose value is the value given"},
	"assignable":     {operands: []valueType{anyValue, text}, gives: truth, says: "whether a resolved type is assignable to the type named; unknown when it is unresolved"},
	"reportable":     {operands: []valueType{node}, gives: truth, says: "whether a finding on the node can be reported: it is in code the project can change"},
	"proven":         {operands: []valueType{truth}, gives: truth, says: "true only when the truth is proven true: an unknown reads as false, for an exclusion that must not hide what it cannot prove"},
	"exemptedBy":     {operands: []valueType{text, node}, gives: truth, says: "whether a library the project holds excuses the node under the exemption tag named, by its resolved symbol"},
	"identity":       {operands: []valueType{text}, gives: text, says: "the type a library names by the identity given, such as \"option\"; unknown when no library names it"},
	"referrers":      {operands: []valueType{node}, gives: list, says: "every node in the program that refers to the declaration: its calls and reads"},
	"declarationOf":  {operands: []valueType{node}, gives: node, says: "the declaration a call's target or a reference resolves to; unknown when the compiler did not resolve it"},
	"typeDeclaration": {operands: []valueType{text}, gives: node, says: "the project's declaration of the type a symbol or resolved name names; none when the project declares no such type"},
	"position":       {operands: []valueType{node}, gives: number, says: "the node's place among the nodes in the same role of its parent, counted from 0"},
	"programEnums":   {operands: []valueType{}, gives: list, says: "the case values of every enum the program declares, one list per enum of two or more cases"},
	"siblings":       {operands: []valueType{node}, gives: list, says: "every node in the same role of the node's parent, the node too, in order; empty for no node, unknown under a parent the parser recovered from"},
	"before":         {operands: []valueType{node}, gives: list, says: "the siblings ahead of the node, in order"},
	"ownAncestors":   {operands: []valueType{node}, gives: list, says: "every node above the node, nearest first, up to the function it is written in"},
	"trailingCount":  {operands: []valueType{list, iteratorName, truth}, gives: number, says: "how many items at the end of the list the body holds for, back to the first it does not"},
	"flatten":        {operands: []valueType{list}, gives: list, says: "the items of a list of lists, as one list"},
	"reads":          {operands: []valueType{text, text}, gives: truth, says: "whether a name or text reads as the judgement named: third-person, question, bare-state, bare-verb, relational-compound, history, strawman, fixture-marker"},
	"startsWith":     {operands: []valueType{text, text}, gives: truth, says: "whether a text begins with another"},
	"keys":           {operands: []valueType{anyValue}, gives: list, says: "the field names of a record, such as a map parameter, sorted"},
	"get":            {operands: []valueType{anyValue, text}, gives: anyValue, says: "a record's field by its name; none when it has no such field"},
	"withinModule":   {operands: []valueType{text, text}, gives: truth, says: "whether a module is the one named or inside it, whatever separator the language spells modules with"},
	"contains":       {operands: []valueType{text, text}, gives: truth, says: "whether a text holds another"},
	"isOdd":          {operands: []valueType{number}, gives: truth, says: "whether a whole number is odd"},
	"list":           {operands: []valueType{anyValue}, repeating: true, optional: 1, gives: list, says: "the values given, as one list"},
	"last":           {operands: []valueType{list}, gives: anyValue, says: "the last item of a list; no value for an empty one"},
	"first":          {operands: []valueType{list}, gives: anyValue, says: "the first item of a list; no value for an empty one"},
	"has":            {operands: []valueType{list, anyValue}, gives: truth, says: "whether a list holds a value equal to the one given"},
	"text":           {operands: []valueType{anyValue}, gives: text, says: "a text, number, truth or no value written as text: 3 reads \"3\", for building a message"},
	"toNumber":       {operands: []valueType{text}, gives: number, says: "a number written as text, in decimal, hexadecimal, octal or binary, with digit separators; unknown for text that is no number"},
	"length":         {operands: []valueType{text}, gives: number, says: "how many characters a text holds"},
	"programTexts":   {operands: []valueType{}, gives: list, says: "the value of every string literal the program writes, once each"},
	"reverse":        {operands: []valueType{list}, gives: list, says: "the list's items, last first"},
	"snakeCase":      {operands: []valueType{text}, gives: text, says: "a camelCase name in snake_case: postalCode reads postal_code"},
	"baseName":       {operands: []valueType{text}, gives: text, says: "a path's last segment without its extension: config/app.php reads app"},
	"join":           {operands: []valueType{list, text}, gives: text, says: "the texts of a list written one after another with the separator between each two"},
	"concat":         {operands: []valueType{text, text}, repeating: true, gives: text, says: "the texts written one after another"},
	"at":             {operands: []valueType{list, number}, gives: anyValue, says: "the item of a list at a position, counted from 0; no value past its end"},
	"in":             {operands: []valueType{anyValue, anyValue}, repeating: true, gives: truth, says: "whether the first value equals any of the values written after it; for a list held in a value, use has"},
	"is":             {operands: []valueType{node, text}, gives: truth, says: "whether a node is of the kind, or in the category, named"},
	"sort":           {operands: []valueType{list}, gives: list, says: "the list's items in order: numbers by size, anything else by its written form"},
	"pair":           {operands: []valueType{anyValue, anyValue}, gives: list, says: "the two values as one, to compare or sort together"},
	"distinctCount":  {operands: []valueType{list}, gives: number, says: "how many different values a list holds; unknown when one of them is"},
	"sum":            {operands: []valueType{list}, gives: number, says: "the sum of a list of numbers"},
	"encloses":       {operands: []valueType{text, text}, gives: truth, says: "whether the first module holds the second: the second goes on from the first past a separator"},
	"lexLess":        {operands: []valueType{text, text}, gives: truth, says: "whether the first text sorts before the second"},
	"syntaxWeight":   {operands: []valueType{node}, gives: number, says: "how many nodes a subtree holds, no-op statements left out"},
	"hasEffect":      {operands: []valueType{node, text}, gives: truth, says: "whether the library a call's resolved target belongs to states the effect named for it, at the version the project's lock file holds; unknown when the target or the version is. The effects are those the package files under rules/packages state: executes-query, database-read, deferred-query, mutates-receiver, iterates-receiver, builds-mutable, fresh-every-call, live-view, tests-membership, decodes-untyped, cast-result-type, blocks-thread, blocks-on-task, completed-task and runs-on-thread"},
}

// operatorNames is the rule language's operators, in the order a reference lists them.
func operatorNames() []string {
	var names []string
	for name := range operators {
		names = append(names, name)
	}
	slices.Sort(names)

	return names
}

// Reference is the rule language's operators as a table, for the documents that teach writing a rule.
func Reference() string {
	var table strings.Builder
	table.WriteString("| Operator | Operands | Gives | What it says |\n|---|---|---|---|\n")
	for _, name := range operatorNames() {
		spec := operators[name]
		var operands []string
		for _, operand := range spec.operands {
			operands = append(operands, string(operand))
		}
		if spec.repeating {
			operands = append(operands, "…")
		}
		fmt.Fprintf(&table, "| `%s` | %s | %s | %s |\n", name, strings.Join(operands, ", "), spec.gives, spec.says)
	}

	return table.String()
}
