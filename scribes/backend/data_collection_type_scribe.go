package backend

import (
	"strings"

	rules "github.com/jessegall/code-commandments/detectors/backend/spatie"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/scribes"
)

func init() {
	scribes.Fixes(rules.DataCollectionTypeDetector{}, func() scribes.Scribe { return DataCollectionTypeScribe{} })
}

// DataCollectionTypeScribe retypes a field typed DataCollection, whose element #[DataCollectionOf] already names, as an array, its docblock with it, and drops the import nothing spells any more.
type DataCollectionTypeScribe struct{}

func (DataCollectionTypeScribe) Rewrite(findings []engine.Match, _ *engine.Codebase) (scribes.Rewrites, error) {
	draft := scribes.NewDraft()
	var files []string
	retyped := map[string]engine.Match{}
	for _, finding := range findings {
		if kind := finding.Kind(); (kind == "Param" || kind == "Stmt_Property") && retype(draft, finding) {
			if _, seen := retyped[finding.File()]; !seen {
				files = append(files, finding.File())
			}
			retyped[finding.File()] = finding
		}
	}
	for _, file := range files {
		finding := retyped[file]
		class := php.Node{Match: finding}.EnclosingClassLike()
		if class.Exists() && !stillNamesDataCollection(class.Match) {
			For(draft, finding).DropImport(spatie.DataCollection)
		}
	}

	return draft.Rewrites(), nil
}

func retype(draft *scribes.Draft, field engine.Match) bool {
	typeName, named := dataCollectionNameIn(field.Child("type"))
	if !named || !carriesDataCollectionOf(field) {
		return false
	}
	writer := For(draft, field)
	writer.Replace(typeName, "array")
	name, known := fieldName(field)
	if !known {
		return true
	}
	for _, documented := range []engine.Match{field, field.Parent()} {
		doc, found := php.Node{Match: documented}.DocComment()
		if !found {
			continue
		}
		if retypedText := php.DocblockRetype(doc.Text, name, spatie.DataCollection, "array"); retypedText != doc.Text {
			writer.ReplaceDocblock(documented, retypedText)
		}
	}

	return true
}

func stillNamesDataCollection(class engine.Match) bool {
	for _, declaration := range declarationsIn(class) {
		if _, named := dataCollectionNameIn(declaration.Child("type")); named && !carriesDataCollectionOf(declaration) {
			return true
		}
	}
	for _, node := range append([]engine.Match{class}, class.ChildrenIn("stmts")...) {
		if doc, found := (php.Node{Match: node}).DocComment(); found && php.DocblockMentionsType(asFixed(doc.Text, class), spatie.DataCollection) {
			return true
		}
	}

	return false
}

func asFixed(text string, class engine.Match) string {
	for _, declaration := range declarationsIn(class) {
		name, known := fieldName(declaration)
		if _, named := dataCollectionNameIn(declaration.Child("type")); known && named && carriesDataCollectionOf(declaration) {
			text = php.DocblockRetype(text, name, spatie.DataCollection, "array")
		}
	}

	return text
}

func declarationsIn(class engine.Match) []engine.Match {
	var declarations []engine.Match
	for _, statement := range class.ChildrenIn("stmts") {
		switch statement.Kind() {
		case "Stmt_Property":
			declarations = append(declarations, statement)
		case "Stmt_ClassMethod":
			declarations = append(declarations, php.Params(statement)...)
		}
	}

	return declarations
}

func fieldName(declaration engine.Match) (string, bool) {
	if declaration.Kind() == "Param" {
		variable := declaration.Child("var")

		return variable.Name(), variable.Kind() == "Expr_Variable" && variable.Name() != ""
	}
	if declared := declaration.ChildrenIn("props"); declaration.Kind() == "Stmt_Property" && len(declared) > 0 {
		return declared[0].Child("name").Name(), true
	}

	return "", false
}

func dataCollectionNameIn(typed engine.Match) (engine.Match, bool) {
	switch kind := typed.Kind(); {
	case strings.HasPrefix(kind, "Name"):
		return typed, strings.TrimLeft(typed.Name(), `\`) == spatie.DataCollection
	case kind == "NullableType":
		return dataCollectionNameIn(typed.Child("type"))
	case kind == "UnionType":
		for _, member := range typed.ChildrenIn("types") {
			if strings.HasPrefix(member.Kind(), "Name") && strings.TrimLeft(member.Name(), `\`) == spatie.DataCollection {
				return member, true
			}
		}
	}

	return engine.Match{}, false
}

func carriesDataCollectionOf(field engine.Match) bool {
	for _, group := range field.ChildrenIn("attrGroups") {
		for _, attribute := range group.ChildrenIn("attrs") {
			name := strings.TrimLeft(attribute.Child("name").Name(), `\`)
			if name == "DataCollectionOf" || strings.HasSuffix(name, `\DataCollectionOf`) {
				return true
			}
		}
	}

	return false
}
