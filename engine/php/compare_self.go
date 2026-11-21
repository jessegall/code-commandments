package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// EnumComparison holds the operands of a strict comparison with a case of the subject's enum.
type EnumComparison struct {
	Subject engine.Match
	Case    engine.Match
	Enum    string
	Negated bool
}

func CompareEnumCases(expression engine.Match) []EnumComparison {
	var negated bool
	switch expression.Kind() {
	case "Expr_BinaryOp_BooleanOr", "Expr_BinaryOp_LogicalOr":
	case "Expr_BinaryOp_BooleanAnd", "Expr_BinaryOp_LogicalAnd":
		negated = true
	default:
		if compared, valid := CompareEnumCase(expression); valid {
			return []EnumComparison{compared}
		}
		return nil
	}
	var comparisons []EnumComparison
	var accept func(engine.Match) bool
	accept = func(node engine.Match) bool {
		if node.Kind() == expression.Kind() {
			return accept(node.Child("left")) && accept(node.Child("right"))
		}
		compared, valid := CompareEnumCase(node)
		if !valid || compared.Negated != negated || compared.Subject.Kind() != "Expr_Variable" {
			return false
		}
		if len(comparisons) > 0 && (compared.Enum != comparisons[0].Enum || compared.Subject.Name() != comparisons[0].Subject.Name()) {
			return false
		}
		comparisons = append(comparisons, compared)
		return true
	}
	if !accept(expression) {
		return nil
	}

	return comparisons
}

func CompareEnumCase(comparison engine.Match) (EnumComparison, bool) {
	negated := comparison.Kind() == "Expr_BinaryOp_NotIdentical"
	if !negated && comparison.Kind() != "Expr_BinaryOp_Identical" {
		return EnumComparison{}, false
	}
	left, right := comparison.Child("left"), comparison.Child("right")
	for _, operands := range [][2]engine.Match{{left, right}, {right, left}} {
		subject, literal := operands[0], operands[1]
		owner, name := enumCase(literal)
		typed := Written(subject.Typed())
		if owner == "" || strings.TrimLeft(typed.Class(), `\`) != owner {
			continue
		}
		declaration, _ := ProgramOf(comparison.Codebase()).Declaration(owner)
		if slices.ContainsFunc(declaration.ChildrenIn("stmts"), func(member engine.Match) bool {
			return member.Kind() == "Stmt_EnumCase" && member.Name() == name
		}) {
			return EnumComparison{Subject: subject, Case: literal, Enum: owner, Negated: negated}, true
		}
	}

	return EnumComparison{}, false
}

func EnumComparisonKeepsNarrowing(comparison engine.Match) bool {
	for child, parent := comparison, comparison.Parent(); parent.Exists(); child, parent = parent, parent.Parent() {
		switch parent.Kind() {
		case "Stmt_If", "Stmt_ElseIf", "Stmt_While", "Stmt_Do", "Expr_Ternary":
			return child.Field() == "cond"
		case "Expr_BinaryOp_BooleanAnd", "Expr_BinaryOp_BooleanOr", "Expr_BinaryOp_LogicalAnd", "Expr_BinaryOp_LogicalOr":
			return true
		case "Stmt_Match", "Stmt_Switch", "Expr_Match", "MatchArm":
			return true
		}
		if parent.Is(engine.Function) {
			break
		}
	}

	return false
}

func EnumComparisonContainsComment(comparison engine.Match) bool {
	span := comparison.Node().Span
	return slices.ContainsFunc(comparison.Source().File.Comments, func(comment contract.Comment) bool {
		return comment.Span.Start >= span.Start && comment.Span.End <= span.End
	})
}

func EnumComparisonMethod(codebase *engine.Codebase, enum, trait, name string, count int) bool {
	program := ProgramOf(codebase)
	declaration, found := program.Declaration(enum)
	if !found || declaration.IsInDependency() {
		return false
	}
	traits := traitsOf(declaration.Node())
	if trait != "" && !slices.Contains(traits, trait) {
		return false
	}
	owner := TypesOf(codebase).DeclaringClassOfMethod(enum, name)
	if declared, known := program.Declaration(owner); known {
		if method, found := Method(declared, name); found {
			return acceptsEnumCases(method, enum, count)
		}
	}
	if trait == "" {
		trait = `App\Support\Enums\CompareSelf`
	}
	if !slices.Contains(traits, trait) {
		return false
	}
	declared, _ := program.Declaration(trait)
	magic, known := Method(declared, "__call")
	doc, documented := (Node{Match: declared}).DocComment()

	if !known || !magic.HasModifier("public") || magic.HasModifier("static") || !documented {
		return false
	}
	returned := Written(magic.Node().Written)
	return returned.SimpleName() == "bool" && !returned.IsNullable() &&
		slices.ContainsFunc(DocLines(doc.Text), func(line string) bool {
			fields := strings.Fields(line)
			return len(fields) >= 3 && fields[0] == "@method" && fields[1] == "bool" && strings.HasPrefix(fields[2], name+"(")
		})
}

func acceptsEnumCases(method engine.Match, enum string, count int) bool {
	if !method.HasModifier("public") || method.HasModifier("static") ||
		Written(method.Node().Written).SimpleName() != "bool" || Written(method.Node().Written).IsNullable() {
		return false
	}
	params := Params(method)
	if len(params) != 1 {
		return false
	}
	param := params[0]
	if param.HasFlag("by-ref") || count > 1 && !param.HasFlag("variadic") {
		return false
	}
	if param.Node().Written == nil {
		return true
	}
	switch strings.TrimLeft(Written(param.Node().Written).SimpleName(), `\`) {
	case "self", "static", "mixed", "object", "UnitEnum":
		return true
	case "BackedEnum":
		class, _ := ProgramOf(method.Codebase()).Declaration(enum)
		return class.Child("scalarType").Exists()
	}

	return strings.TrimLeft(Written(param.Node().Written).Class(), `\`) == enum
}
