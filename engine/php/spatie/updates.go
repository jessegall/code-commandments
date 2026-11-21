package spatie

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

type guardedUpdate struct {
	function *contract.Node
	source   string
	target   string
	class    string
	fields   map[string]bool
}

var sparseUpdates = engine.Memoised(findSparseUpdates)

func findSparseUpdates(codebase *engine.Codebase) map[string]bool {
	var groups []guardedUpdate
	for _, test := range engine.ProjectMatches(codebase.WhereKind("Expr_Instanceof").Get()) {
		field := test.Child("expr")
		condition := test.Parent()
		branch := condition.Parent()
		if strings.TrimLeft(test.Child("class").Name(), `\`) != Optional ||
			field.Kind() != "Expr_PropertyFetch" || condition.Kind() != "Expr_BooleanNot" ||
			branch.Kind() != "Stmt_If" || branch.Child("cond").Node() != condition.Node() ||
			branch.Child("else").Exists() || branch.Child("elseifs").Exists() {
			continue
		}
		statements := branch.ChildrenIn("stmts")
		if len(statements) != 1 || statements[0].Kind() != "Stmt_Expression" {
			continue
		}
		source := field.Child("var")
		if source.Kind() != "Expr_Variable" || source.Name() == "this" || field.Child("name").Kind() != "Identifier" {
			continue
		}
		target := updateTarget(statements[0].Child("expr"), field)
		if target == "" {
			continue
		}
		class := updateSourceClass(source)
		if class == "" {
			continue
		}
		function := (php.Node{Match: test}).EnclosingFunctionLike()
		if branch.Parent().Node() != function.Node() {
			continue
		}
		index := slices.IndexFunc(groups, func(group guardedUpdate) bool {
			return group.function == function.Node() && group.source == source.Name() && group.target == target && group.class == class
		})
		if index < 0 {
			index = len(groups)
			groups = append(groups, guardedUpdate{function.Node(), source.Name(), target, class, map[string]bool{}})
		}
		groups[index].fields[field.Child("name").Name()] = true
	}

	updates := map[string]bool{}
	for _, update := range groups {
		declaration, found := php.ProgramOf(codebase).Declaration(update.class)
		if found && !slices.ContainsFunc(php.PublicInstanceFields(declaration), func(field php.Field) bool {
			return !update.fields[field.Name]
		}) {
			updates[update.class] = true
		}
	}
	return updates
}

func updateSourceClass(source engine.Match) string {
	program := php.ProgramOf(source.Codebase())
	declared := php.TypesOf(source.Codebase()).TypeAtRead(source)
	if declared != Data && !program.Extends(declared, Data) {
		return ""
	}
	function := (php.Node{Match: source}).EnclosingFunctionLike()
	for function.Exists() {
		if slices.ContainsFunc(php.Params(function.Match), func(param engine.Match) bool {
			return param.Child("var").Name() == source.Name()
		}) {
			break
		}
		function = function.Up().EnclosingFunctionLike()
	}
	if documented := php.DocumentedParamClass(function.Match, source.Name()); program.Extends(documented, declared) {
		return documented
	}
	return declared
}

func updateTarget(call, field engine.Match) string {
	target := call.Child("var")
	if call.Kind() != "Expr_MethodCall" || target.Kind() != "Expr_Variable" || target.SameSyntax(field.Child("var")) {
		return ""
	}
	name := call.Child("name")
	if name.Kind() != "Identifier" || !strings.EqualFold(name.Name(), "set"+field.Child("name").Name()) {
		return ""
	}
	if args := php.Arguments(call); len(args) == 1 && args[0].Child("value").SameSyntax(field) {
		return target.Name()
	}
	return ""
}
