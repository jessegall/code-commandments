package laravel

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// MinSharedCalls is how many domain calls two entry points share before they are one operation written twice.
const MinSharedCalls = 3

// infrastructureThreshold is how many entry points may make a call before it is infrastructure, not the operation.
const infrastructureThreshold = 3

// BoundaryOperations is every public entry point, an HTTP action, a console command or an MCP tool, with the calls
// into the codebase's own classes it makes.
type BoundaryOperations struct {
	order   []string
	entries map[string]boundaryEntry
	usage   map[string]int
}

type boundaryEntry struct {
	kind  string
	calls []string
}

var boundaryOperations = php.Memoised(readBoundaryOperations)

// BoundaryOperationsOf is the codebase's boundary operations.
func BoundaryOperationsOf(codebase *engine.Codebase) *BoundaryOperations {
	return boundaryOperations.Of(codebase)
}

// TwinsOf is every entry point of another kind that performs the same operation as the class's method: at least
// MinSharedCalls domain calls in common that are not infrastructure.
func (b *BoundaryOperations) TwinsOf(fqcn, method string) []string {
	subject, ok := b.entries[fqcn+"::"+method]
	if !ok {
		return nil
	}
	var twins []string
	for _, id := range b.order {
		entry := b.entries[id]
		if entry.kind != subject.kind && len(b.shared(subject.calls, entry.calls)) >= MinSharedCalls {
			twins = append(twins, id)
		}
	}

	return twins
}

func (b *BoundaryOperations) shared(one, other []string) []string {
	var shared []string
	for _, call := range one {
		if slices.Contains(other, call) && b.usage[call] <= infrastructureThreshold {
			shared = append(shared, call)
		}
	}

	return shared
}

func readBoundaryOperations(codebase *engine.Codebase) *BoundaryOperations {
	operations := &BoundaryOperations{entries: map[string]boundaryEntry{}, usage: map[string]int{}}
	program := php.ProgramOf(codebase)
	types := php.TypesOf(codebase)
	actions := RouteActionsOf(codebase)
	for _, file := range codebase.Of(contract.PHP).Files() {
		for _, node := range file.Nodes() {
			if node.Kind != "Stmt_ClassMethod" {
				continue
			}
			method := file.Match(node.ID)
			kind := kindOf(program, actions, method)
			if kind == "" || !php.IsPublic(node) {
				continue
			}
			calls := domainCalls(program, types, method)
			if len(calls) < MinSharedCalls {
				continue
			}
			id := php.EnclosingClassName(method) + "::" + php.EnclosingFunctionName(method)
			if _, known := operations.entries[id]; !known {
				operations.order = append(operations.order, id)
			}
			operations.entries[id] = boundaryEntry{kind: kind, calls: calls}
			for _, call := range calls {
				operations.usage[call]++
			}
		}
	}

	return operations
}

func kindOf(program *php.Program, actions *RouteActions, method engine.Match) string {
	class := php.EnclosingClassName(method)
	for _, kind := range BoundaryKinds {
		if program.IsA(class, kind.Base) {
			return kind.Name
		}
	}
	if actions.IsAction(class, php.EnclosingFunctionName(method)) {
		return "http"
	}

	return ""
}

// domainCalls is every method the entry point sends to a class the codebase declares, `$this` aside, once each.
func domainCalls(program *php.Program, types *php.Types, method engine.Match) []string {
	calls := []string{}
	self := php.EnclosingClassName(method)
	for _, statement := range method.Children() {
		if statement.Node().Field != "stmts" {
			continue
		}
		for _, call := range append([]engine.Match{statement}, descendants(statement)...) {
			name, receiver := call.Child("name"), call.Child("var")
			if call.Kind() != "Expr_MethodCall" || name.Kind() != "Identifier" || (receiver.Kind() == "Expr_Variable" && receiver.Name() == "this") {
				continue
			}
			class := types.TypeIn(receiver, method, self)
			if _, declared := program.Declaration(class); class == "" || !declared {
				continue
			}
			if key := class + "::" + name.Name(); !slices.Contains(calls, key) {
				calls = append(calls, key)
			}
		}
	}

	return calls
}
