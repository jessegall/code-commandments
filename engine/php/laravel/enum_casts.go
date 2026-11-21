package laravel

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

const attributeCast = `Illuminate\Database\Eloquent\Casts\Attribute`

var casts = engine.Memoised(readEnumCasts)

func init() {
	engine.OffersPropertyTypes(contract.PHP, func(codebase *engine.Codebase, model, field string) *contract.Type {
		return casts.Of(codebase)[model][field]
	})
}

func readEnumCasts(codebase *engine.Codebase) map[string]map[string]*contract.Type {
	program := php.ProgramOf(codebase)
	models := map[string]map[string]*contract.Type{}
	for name, model := range program.Declarations() {
		if model.Kind() != "Stmt_Class" || !program.Extends(name, Model) || program.HasSubclass(name) {
			continue
		}
		casts := declaredCasts(model, program)
		for field := range casts {
			if hasAccessor(model, field) {
				delete(casts, field)
			}
		}
		if len(casts) > 0 {
			models[name] = casts
		}
	}
	return models
}

func declaredCasts(model engine.Match, program *php.Program) map[string]*contract.Type {
	var arrays []engine.Match
	for _, field := range php.Fields(model) {
		if field.Name == "casts" {
			arrays = append(arrays, field.Node.Child("default"))
			break
		}
	}
	if method, exists := php.Method(model, "casts"); exists {
		statements := method.ChildrenIn("stmts")
		if len(statements) != 1 || statements[0].Kind() != "Stmt_Return" {
			return nil
		}
		arrays = append(arrays, statements[0].Child("expr"))
	}
	types := map[string]*contract.Type{}
	for _, array := range arrays {
		if array.Kind() != "Expr_Array" {
			return nil
		}
		for _, item := range array.ChildrenIn("items") {
			key, value := item.Child("key"), item.Child("value")
			field, known := key.Text()
			if key.Kind() != "Scalar_String" || !known {
				return nil
			}
			delete(types, field)
			if value.Kind() != "Expr_ClassConstFetch" || !strings.EqualFold(value.Child("name").Name(), "class") {
				continue
			}
			class := strings.TrimLeft(value.Child("class").Name(), `\`)
			if program.IsEnum(class) {
				types[field] = &contract.Type{Text: "?" + class, Kind: "named", Name: class, Nullable: contract.Stated(true), Origin: "inferred"}
			}
		}
	}
	return types
}

func hasAccessor(model engine.Match, field string) bool {
	accessor := strings.ToLower(strings.NewReplacer("_", "", "-", "", " ", "").Replace(field))
	return slices.ContainsFunc(append(php.Methods(model), model.InheritedMembers()...), func(method engine.Match) bool {
		if method.Kind() != "Stmt_ClassMethod" {
			return false
		}
		name := strings.ToLower(method.Name())
		return name == "get"+accessor+"attribute" ||
			name == accessor && php.Written(method.Node().Written).Class() == attributeCast
	})
}
