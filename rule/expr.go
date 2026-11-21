package rule

import (
	"bytes"
	"encoding/json"
	"fmt"
	"strings"
)

// Expr is one expression of a commandments:neutral-rule/v2 file, read from its JSON: a list whose first item is the
// operator it applies, a string beginning with $ that refers to a bound name and the roles read through it, an object
// holding a case's arms, or a plain value.
type Expr struct {
	Operator  string
	Operands  []Expr
	Reference []string
	Arms      map[string]Expr
	Value     any
}

func (e *Expr) UnmarshalJSON(text []byte) error {
	trimmed := bytes.TrimSpace(text)
	switch {
	case bytes.HasPrefix(trimmed, []byte("[")):
		return e.call(trimmed)
	case bytes.HasPrefix(trimmed, []byte("{")):
		return json.Unmarshal(trimmed, &e.Arms)
	}

	if err := json.Unmarshal(trimmed, &e.Value); err != nil {
		return err
	}
	if written, isText := e.Value.(string); isText && strings.HasPrefix(written, "$") {
		e.Value, e.Reference = nil, strings.Split(strings.TrimPrefix(written, "$"), ".")
	}

	return nil
}

func (e *Expr) call(text []byte) error {
	var items []json.RawMessage
	if err := json.Unmarshal(text, &items); err != nil {
		return err
	}
	if len(items) == 0 || json.Unmarshal(items[0], &e.Operator) != nil || e.Operator == "" || strings.HasPrefix(e.Operator, "$") {
		return fmt.Errorf("an expression written as a list starts with the operator it applies, such as [\"and\", …]")
	}
	e.Operands = make([]Expr, len(items)-1)
	for at, item := range items[1:] {
		if err := json.Unmarshal(item, &e.Operands[at]); err != nil {
			return err
		}
	}

	return nil
}

// IsCall says whether the expression applies an operator or a definition.
func (e Expr) IsCall() bool {
	return e.Operator != ""
}

// IsReference says whether the expression refers to a bound name.
func (e Expr) IsReference() bool {
	return len(e.Reference) > 0
}

// IsArms says whether the expression is a case's arms.
func (e Expr) IsArms() bool {
	return e.Arms != nil
}

// Name is the bound name a reference starts at.
func (e Expr) Name() string {
	return e.Reference[0]
}

// Written is the expression as the rule file spells it, for a diagnostic.
func (e Expr) Written() string {
	switch {
	case e.IsReference():
		return "$" + strings.Join(e.Reference, ".")
	case e.IsCall():
		return "[" + `"` + e.Operator + `", …]`
	case e.IsArms():
		return "the arms {…}"
	}
	written, _ := json.Marshal(e.Value)

	return string(written)
}

// Each calls visit on the expression and every expression inside it, outermost first.
func (e Expr) Each(visit func(Expr)) {
	visit(e)
	for _, operand := range e.Operands {
		operand.Each(visit)
	}
	for _, arm := range e.Arms {
		arm.Each(visit)
	}
}
