package laravel

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// ResponseSurface is every class a response is built from: what an Inertia render is handed, and what a
// controller's public methods declare or return.
type ResponseSurface struct {
	bound map[string]bool
}

var responseSurfaces = php.Memoised(readResponseSurface)

// ResponseSurfaceOf is the codebase's response surface.
func ResponseSurfaceOf(codebase *engine.Codebase) *ResponseSurface {
	return responseSurfaces.Of(codebase)
}

// IsResponseBound says whether a response is built from the class.
func (s *ResponseSurface) IsResponseBound(fqcn string) bool {
	return fqcn != "" && s.bound[strings.TrimLeft(fqcn, `\`)]
}

func readResponseSurface(codebase *engine.Codebase) *ResponseSurface {
	surface := &ResponseSurface{bound: map[string]bool{}}
	program := php.ProgramOf(codebase)
	types := php.TypesOf(codebase)
	for _, file := range codebase.Of(contract.PHP).Files() {
		for _, node := range file.Nodes() {
			if render := file.Match(node.ID); RendersInertiaPage(render) {
				surface.collect(php.Arguments(render), types)
			}
		}
		for _, node := range file.Nodes() {
			if node.Kind == "Stmt_Class" && program.Extends(node.Symbol, Controller) {
				surface.collectControllerReturns(file.Match(node.ID), types)
			}
		}
	}

	return surface
}

func (s *ResponseSurface) collectControllerReturns(controller engine.Match, types *php.Types) {
	for _, method := range php.Methods(controller) {
		if !php.IsPublic(method.Node()) {
			continue
		}
		if returned := php.Written(method.Node().Returns).Class(); returned != "" {
			s.bound[strings.TrimLeft(returned, `\`)] = true
		}
		for _, statement := range method.Children() {
			if statement.Node().Field != "stmts" {
				continue
			}
			for _, node := range append([]engine.Match{statement}, descendants(statement)...) {
				if expression := node.Child("expr"); node.Kind() == "Stmt_Return" && expression.Exists() {
					s.collect([]engine.Match{expression}, types)
				}
			}
		}
	}
}

func (s *ResponseSurface) collect(nodes []engine.Match, types *php.Types) {
	for _, node := range nodes {
		for _, construction := range append([]engine.Match{node}, descendants(node)...) {
			if isConstruction(construction) {
				s.bound[strings.TrimLeft(construction.Child("class").Name(), `\`)] = true
			}
		}
	}
	for _, node := range nodes {
		for _, expression := range candidateExpressions(node) {
			if class := resolveType(expression, types); class != "" {
				s.bound[class] = true
			}
		}
	}
}

func candidateExpressions(node engine.Match) []engine.Match {
	switch {
	case node.Kind() == "Arg":
		return candidateExpressions(node.Child("value"))
	case node.Kind() == "Expr_Array":
		var expressions []engine.Match
		for _, item := range node.Children() {
			if item.Node().Field == "items" {
				expressions = append(expressions, candidateExpressions(item.Child("value"))...)
			}
		}

		return expressions
	case node.Exists() && node.Node().Role == "expression":
		return []engine.Match{node}
	}

	return nil
}

func resolveType(expression engine.Match, types *php.Types) string {
	if isConstruction(expression) {
		return strings.TrimLeft(expression.Child("class").Name(), `\`)
	}

	return strings.TrimLeft(types.TypeOf(expression), `\`)
}

func isConstruction(node engine.Match) bool {
	return (node.Kind() == "Expr_StaticCall" || node.Kind() == "Expr_New") && isName(node.Child("class"))
}
