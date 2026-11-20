package laravel

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

const MinSharedCalls = 3

const infrastructureThreshold = 3

// BoundaryOperations is every public entry point, an HTTP action, a console command or an MCP tool, with the calls into the codebase's own classes it makes.
type BoundaryOperations struct {
	order   []string
	entries map[string]boundaryEntry
	usage   map[string]int
}

type boundaryEntry struct {
	kind  string
	calls []string
	flows []callFlow
}

// callFlow is an ordered pair of calls where one supplies an argument to the other.
type callFlow struct {
	producer string
	consumer string
}

var boundaryOperations = engine.Memoised(readBoundaryOperations)

func BoundaryOperationsOf(codebase *engine.Codebase) *BoundaryOperations {
	return boundaryOperations.Of(codebase)
}

func (b *BoundaryOperations) TwinsOf(fqcn, method string) []string {
	subject, ok := b.entries[fqcn+"::"+method]
	if !ok {
		return nil
	}
	var twins []string
	for _, id := range b.order {
		entry := b.entries[id]
		if entry.kind != subject.kind && b.sharesOperation(subject, entry) {
			twins = append(twins, id)
		}
	}

	return twins
}

func (b *BoundaryOperations) sharesOperation(one, other boundaryEntry) bool {
	var shared []string
	for _, call := range one.calls {
		if slices.Contains(other.calls, call) && b.usage[call] <= infrastructureThreshold {
			shared = append(shared, call)
		}
	}
	if len(shared) >= MinSharedCalls {
		return true
	}
	if len(shared) != 2 {
		return false
	}

	return slices.ContainsFunc(one.flows, func(flow callFlow) bool {
		return slices.Contains(shared, flow.producer) && slices.Contains(shared, flow.consumer) && slices.Contains(other.flows, flow)
	})
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
			if kind == "" || !php.IsPublic(node) || method.IsInDependency() {
				continue
			}
			entry := readBoundaryEntry(program, types, method, kind)
			if len(entry.calls) < 2 {
				continue
			}
			id := php.EnclosingClassName(method) + "::" + php.EnclosingFunctionName(method)
			if _, known := operations.entries[id]; !known {
				operations.order = append(operations.order, id)
			}
			operations.entries[id] = entry
			for _, call := range entry.calls {
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

func readBoundaryEntry(program *php.Program, types *php.Types, method engine.Match, kind string) boundaryEntry {
	entry := boundaryEntry{kind: kind}
	targets := map[engine.Match]string{}
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
			if declaration, declared := program.Declaration(class); !declared || declaration.IsInDependency() {
				continue
			}
			key := class + "::" + name.Name()
			targets[call] = key
			if !slices.Contains(entry.calls, key) {
				entry.calls = append(entry.calls, key)
			}
		}
	}

	for call, consumer := range targets {
		for _, argument := range php.Arguments(call) {
			value := argument.Child("value")
			if value.Kind() == "Expr_Variable" {
				value = php.LocalValueAt(value)
			}
			producer := targets[value]
			if producer != "" && producer != consumer {
				entry.flows = append(entry.flows, callFlow{producer: producer, consumer: consumer})
			}
		}
	}

	return entry
}
