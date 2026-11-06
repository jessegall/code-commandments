package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

var sends = []string{"Expr_MethodCall", "Expr_NullsafeMethodCall", "Expr_StaticCall"}

// Index is the program's call graph: every named method send and static call, by the member it names.
type Index struct {
	program *Program
	byName  map[string][]engine.Match
}

var indexes = Memoised(buildIndex)

func IndexOf(codebase *engine.Codebase) *Index {
	return indexes.Of(codebase)
}

func buildIndex(codebase *engine.Codebase) *Index {
	index := &Index{program: ProgramOf(codebase), byName: map[string][]engine.Match{}}
	files := codebase.Of(contract.PHP).Files()
	for _, kind := range sends {
		for _, file := range files {
			for _, node := range file.Nodes() {
				if node.Kind != kind {
					continue
				}
				name := child(node, "name")
				if name.Kind != "Identifier" {
					continue
				}
				index.byName[name.Name] = append(index.byName[name.Name], file.Match(node.ID))
			}
		}
	}

	return index
}

func (i *Index) CallersOf(fqcn, method string) []engine.Match {
	var callers []engine.Match
	for _, call := range i.byName[method] {
		receiver := StaticCallClass(call)
		if receiver == "" {
			receiver = ReceiverTypeOf(call)
		}
		if receiver != "" && (receiver == fqcn || i.program.Extends(receiver, fqcn)) {
			callers = append(callers, call)
		}
	}

	return callers
}

func StaticCallClass(call engine.Match) string {
	class := call.Child("class")
	if call.Kind() != "Expr_StaticCall" || !isName(class) {
		return ""
	}
	if slices.Contains([]string{"self", "static"}, class.Name()) {
		return EnclosingClassName(call)
	}

	return class.Name()
}

func (i *Index) FunctionCallersOf(function engine.Match) []engine.Match {
	qualified := strings.TrimSuffix(function.Node().Symbol, "()")
	namespace, short := "", qualified
	if at := strings.LastIndex(qualified, `\`); at >= 0 {
		namespace, short = qualified[:at], qualified[at+1:]
	}

	var calls []engine.Match
	for _, call := range functionCalls(function.Codebase())[strings.ToLower(short)] {
		name := call.Child("name")

		switch name.Kind() {
		case "Name_FullyQualified":
			if strings.EqualFold(name.Name(), qualified) {
				calls = append(calls, call)
			}
		case "Name":
			if strings.EqualFold(call.Namespace(), namespace) || namespace == "" && !declaresFunction(call, short) {
				calls = append(calls, call)
			}
		}
	}

	return calls
}

func declaresFunction(call engine.Match, short string) bool {
	return len(call.Codebase().Declarations(call.Namespace()+`\`+short+"()")) > 0
}

func functionCalls(codebase *engine.Codebase) map[string][]engine.Match {
	return engine.Analysis(codebase, "php-function-calls", func(codebase *engine.Codebase) map[string][]engine.Match {
		byName := map[string][]engine.Match{}
		for _, call := range codebase.WhereCall().Get() {
			if call.Kind() != "Expr_FuncCall" {
				continue
			}

			name := call.Child("name").Name()
			short := strings.ToLower(name[strings.LastIndex(name, `\`)+1:])
			byName[short] = append(byName[short], call)
		}

		return byName
	})
}
