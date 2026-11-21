package rule

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

// programRelations are the relations every find may read without defining them: rows built once from the whole
// program, each row carrying the file it lies in, whether that file is a test, and whether it is held for report.
var programRelations = map[string]func(*engine.Codebase) []value{
	importsSource:  importRecords,
	"declarations": declarationRecords,
	"references":   referenceRecords,
	"calls":        callRecords,
	"inherits":     inheritRecords,
	"overrides":    overrideRecords,
	"files":        fileRecords,
}

// programRelationNames are the program relations by name, in order, for a diagnostic.
func programRelationNames() []string {
	names := make([]string, 0, len(programRelations))
	for name := range programRelations {
		names = append(names, name)
	}
	slices.Sort(names)

	return names
}

// placed is a row's own fields with where it lies.
func placed(site engine.Match, fields record) value {
	file := site.Source()
	fields["file"] = known(file.Path)
	fields["module"] = known(file.Module)
	fields["test"] = known(file.Purpose() != engine.Ordinary)
	fields["held"] = known(site.Codebase().Reports(file))

	return known(fields)
}

// declarationKinds are the kinds whose nodes declare a symbol other files may name.
var declarationKinds = []string{"type", "function", "enum-case", "alias", "binding", "module"}

func declarationRecords(codebase *engine.Codebase) []value {
	var records []value
	for _, site := range codebase.WhereKind(declarationKinds...).Get() {
		node := site.Node()
		if node.Symbol == "" {
			continue
		}
		records = append(records, placed(site, record{"node": known(site), "symbol": known(node.Symbol), "kind": known(node.Kind),
			"name": known(node.Name), "form": known(node.Form), "dependency": known(site.IsInDependency())}))
	}

	return records
}

// referenceUse is what a node naming a declaration does with it.
func referenceUse(site engine.Match) string {
	node, parent := site.Node(), site.Parent()
	role := node.InRole()
	switch {
	case node.Kind == "import" || node.Kind == "import-binding":
		return "import"
	case node.Kind == "annotation":
		return "attribute"
	case role == "bases" || role == "interfaces" || role == "mixins":
		return "inherit"
	case node.Kind == "type-expression":
		return "type"
	case parent.Exists() && parent.Node().Kind == "call" && role == "callee":
		return "call"
	case parent.Exists() && parent.Node().Kind == "construction":
		return "construct"
	}
	if mode, _ := accessMode(site).held.(string); mode != "read" {
		return "write"
	}

	return "read"
}

func referenceRecords(codebase *engine.Codebase) []value {
	var records []value
	for _, site := range codebase.WhereKind("reference", "type-expression", "annotation", "import", "import-binding").Get() {
		node := site.Node()
		if node.Refers == "" {
			continue
		}
		owner := site.EnclosingFunction()
		if !owner.Exists() {
			owner = site.EnclosingType()
		}
		records = append(records, placed(site, record{"site": known(site), "symbol": known(node.Refers), "use": known(referenceUse(site)),
			"owner": known(owner), "ownerSymbol": known(owner.Node().Symbol)}))
	}

	return records
}

func callRecords(codebase *engine.Codebase) []value {
	var records []value
	for _, site := range codebase.WhereKind("call", "construction").Get() {
		callee, dispatch := unknown, "unresolved"
		if target := site.Node().Target; target != nil {
			callee, dispatch = known(target.Symbol), "resolved"
		}
		caller := site.EnclosingFunction()
		records = append(records, placed(site, record{"site": known(site), "caller": known(caller.Node().Symbol), "callerNode": known(caller),
			"callee": callee, "dispatch": known(dispatch), "argumentCount": known(float64(len(site.ChildrenIn("arguments"))))}))
	}

	return records
}

func inheritRecords(codebase *engine.Codebase) []value {
	var records []value
	for _, site := range codebase.WhereKind("type").Get() {
		for _, inherited := range [][2]string{{"bases", "extends"}, {"interfaces", "implements"}, {"mixins", "mixin"}} {
			role, via := inherited[0], inherited[1]
			for _, written := range site.ChildrenIn(role) {
				super := unknown
				if refers := written.Node().Refers; refers != "" {
					super = known(refers)
				}
				records = append(records, placed(site, record{"type": known(site.Node().Symbol), "typeNode": known(site), "super": super, "via": known(via), "at": known(written)}))
			}
		}
	}
	return records
}

func overrideRecords(codebase *engine.Codebase) []value {
	var records []value
	for _, site := range codebase.WhereKind("function").Get() {
		for _, obligation := range site.Node().Obligations {
			records = append(records, placed(site, record{"member": known(site), "memberSymbol": known(site.Node().Symbol),
				"overridden": known(obligation.Symbol), "origin": known(obligation.Origin)}))
		}
	}

	return records
}

func fileRecords(codebase *engine.Codebase) []value {
	var records []value
	for at, file := range codebase.Files() {
		records = append(records, known(record{"path": known(file.Path), "module": known(file.Module), "purpose": known(string(file.Purpose())),
			"language": known(string(file.Language())), "held": known(codebase.Reports(file)), "resolved": known(file.Knows("refers")),
			"fileOrder": known(float64(at)), "test": known(file.Purpose() != engine.Ordinary)}))
	}

	return records
}
