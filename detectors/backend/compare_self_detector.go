package backend

import (
	"cmp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// CompareSelfDetector finds comparisons an enum can answer through its own methods.
type CompareSelfDetector struct {
	trait, equals, notEquals, equalsAny, notEqualsAny string
	minimum                                           int
	excluded                                          []string
}

func init() { detectors.Register(catalog.PHP, CompareSelfDetector{}) }

func (CompareSelfDetector) Sin() sins.Sin         { return backendsins.CompareSelf{} }
func (CompareSelfDetector) FormerNames() []string { return []string{"SuggestCompareSelfTraitProphet"} }
func (CompareSelfDetector) CrossFile()            {}

func (d CompareSelfDetector) Trait(name string) CompareSelfDetector {
	d.trait = strings.TrimLeft(name, `\`)
	return d
}

func (d CompareSelfDetector) EqualsMethod(name string) CompareSelfDetector {
	d.equals = name
	return d
}

func (d CompareSelfDetector) NotEqualsMethod(name string) CompareSelfDetector {
	d.notEquals = name
	return d
}

func (d CompareSelfDetector) EqualsAnyMethod(name string) CompareSelfDetector {
	d.equalsAny = name
	return d
}

func (d CompareSelfDetector) NotEqualsAnyMethod(name string) CompareSelfDetector {
	d.notEqualsAny = name
	return d
}

func (d CompareSelfDetector) MinChain(count int) CompareSelfDetector {
	d.minimum = count
	return d
}

func (d CompareSelfDetector) ExcludeEnums(names ...string) CompareSelfDetector {
	d.excluded = slices.Clone(names)
	return d
}

func (d CompareSelfDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Expr_BinaryOp_Identical", "Expr_BinaryOp_NotIdentical", "Expr_BinaryOp_BooleanOr",
			"Expr_BinaryOp_BooleanAnd", "Expr_BinaryOp_LogicalOr", "Expr_BinaryOp_LogicalAnd").
		Where(func(node engine.Match) bool {
			_, compares := d.replacementFor(node)
			return compares
		}).
		Get()
}

func (d CompareSelfDetector) replacementFor(finding engine.Match) (string, bool) {
	if php.EnumComparisonKeepsNarrowing(finding) {
		return "", false
	}
	comparisons := php.CompareEnumCases(finding)
	if len(comparisons) < max(1, d.minimum) {
		return "", false
	}
	first := comparisons[0]
	if php.EnclosingClassName(finding) == first.Enum || php.EnumComparisonContainsComment(finding) ||
		slices.ContainsFunc(d.excluded, func(name string) bool { return strings.TrimLeft(name, `\`) == first.Enum }) ||
		slices.Contains([]string{"toArray", "jsonSerialize", "render"}, php.EnclosingFunctionName(finding)) {
		return "", false
	}
	method := d.method(first.Negated, len(comparisons))
	negate := false
	if !php.EnumComparisonMethod(finding.Codebase(), first.Enum, d.trait, method, len(comparisons)) {
		if !first.Negated || len(comparisons) != 1 || d.notEquals != "" {
			return "", false
		}
		method = d.method(false, 1)
		if !php.EnumComparisonMethod(finding.Codebase(), first.Enum, d.trait, method, 1) {
			return "", false
		}
		negate = true
	}
	cases := make([]string, 0, len(comparisons))
	for _, compared := range comparisons {
		cases = append(cases, compared.Case.Written())
	}
	receiver := first.Subject.Written()
	if !slices.Contains([]string{"Expr_Variable", "Expr_PropertyFetch", "Expr_MethodCall", "Expr_ClassConstFetch"}, first.Subject.Kind()) {
		receiver = "(" + receiver + ")"
	}
	call := method + "(" + strings.Join(cases, ", ") + ")"
	if php.Written(first.Subject.Typed()).IsNullable() {
		if negate {
			return "(" + receiver + "?->" + call + " !== true)", true
		}
		if first.Negated {
			return "(" + receiver + "?->" + call + " !== false)", true
		}
		return "(" + receiver + "?->" + call + " === true)", true
	}

	if negate {
		return "!" + receiver + "->" + call, true
	}
	return receiver + "->" + call, true
}

func (d CompareSelfDetector) method(negated bool, count int) string {
	switch {
	case negated && count > 1:
		return cmp.Or(d.notEqualsAny, "notEqualsAny")
	case count > 1:
		return cmp.Or(d.equalsAny, "equalsAny")
	case negated:
		return cmp.Or(d.notEquals, "notEquals")
	}
	return cmp.Or(d.equals, "equals")
}
