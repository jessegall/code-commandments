package rule

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"os"
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
)

// orderingSwitch is the one place the cost ordering is read from: "off" asks every rule as written, so a test can
// prove the ordering changes no answer.
const orderingSwitch = "COMMANDMENTS_RULE_ORDER"

func ordering() bool {
	return os.Getenv(orderingSwitch) != "off"
}

// costs are what the operators that walk a subtree or a type hierarchy ask beyond the one every expression costs.
var costs = map[string]int{
	"ownDescendants": 20, "fingerprint": 20, "syntaxWeight": 10, "assignable": 10, "filter": 5, "map": 5,
	"some": 5, "every": 5, "none": 5,
}

// cost is how much an expression asks of the tree, a definition it calls counted whole.
func cost(expression Expr, definitions map[string]definition) int {
	total := 0
	expression.Each(func(each Expr) {
		total += 1 + costs[each.Operator]
		if defined, isDefinition := definitions[each.Operator]; isDefinition {
			total += cost(defined.Expr, nil)
		}
	})

	return total
}

// ordered is the expression with the operands of every conjunction and disjunction asked cheapest first. Those operands
// are independent pure predicates in one scope: a name an iterator binds holds only inside its own operand, so none
// reads what another binds, and Kleene's three-valued and and or answer the same in any order. A case, an iterator
// and every other operator keep the order written.
func ordered(expression Expr, definitions map[string]definition) Expr {
	if !expression.IsCall() {
		return expression
	}
	reordered := expression
	reordered.Operands = make([]Expr, len(expression.Operands))
	for at, operand := range expression.Operands {
		reordered.Operands[at] = ordered(operand, definitions)
	}
	if expression.Operator == "and" || expression.Operator == "or" {
		slices.SortStableFunc(reordered.Operands, func(a, b Expr) int { return cost(a, definitions) - cost(b, definitions) })
	}

	return reordered
}

// compiled is a rule as it is asked: its definitions, where and having ordered cheapest first, unless the switch is off.
func compiled(read file) file {
	if !ordering() {
		return read
	}
	made := read
	made.Definitions = map[string]definition{}
	for name, defined := range read.Definitions {
		made.Definitions[name] = definition{defined.Args, ordered(defined.Expr, read.Definitions)}
	}
	for _, written := range []**Expr{&made.Find.Where, &made.Find.Having} {
		if *written != nil {
			orderedExpr := ordered(**written, read.Definitions)
			*written = &orderedExpr
		}
	}

	return made
}

// CacheKey is the digest a compiled rule is kept under: the effective rule with every definition it calls, the
// contract versions it reads, and whether it is ordered. Changing a definition the rule composes changes the key.
func (r Rule) CacheKey() string {
	effective, _ := json.Marshal(struct {
		Engines     engines
		Parameters  map[string]parameter
		Definitions map[string]definition
		Find        find
		Contract    []int
		Ordered     bool
	}{r.read.Engine, r.read.Parameters, r.read.Definitions, r.read.Find, contract.SupportedVersions, ordering()})
	sum := sha256.Sum256(effective)

	return hex.EncodeToString(sum[:])
}

// compiledRules holds each compiled rule by its cache key, once per process.
var compiledRules sync.Map

func (r Rule) compiled() file {
	key := r.CacheKey()
	if held, found := compiledRules.Load(key); found {
		return held.(file)
	}
	made := compiled(r.read)
	compiledRules.Store(key, made)

	return made
}

func (e Expr) MarshalJSON() ([]byte, error) {
	switch {
	case e.IsReference():
		return json.Marshal("$" + strings.Join(e.Reference, "."))
	case e.IsCall():
		items := []any{e.Operator}
		for _, operand := range e.Operands {
			items = append(items, operand)
		}

		return json.Marshal(items)
	case e.IsArms():
		return json.Marshal(e.Arms)
	}

	return json.Marshal(e.Value)
}

func (b binding) MarshalJSON() ([]byte, error) {
	return json.Marshal([]any{b.Name, b.Expr})
}
