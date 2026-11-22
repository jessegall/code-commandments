package config

import (
	"reflect"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
)

const phpToolClassNamespace = `JesseGall\CodeCommandments\`

var namespaces = map[string]string{
	"backend":    "Backend",
	"frontend":   "Frontend",
	"python":     "Python",
	"csharp":     "CSharp",
	"concurrent": "Concurrent",
	"laravel":    "Laravel",
	"phptypes":   "PhpTypes",
	"spatie":     "Spatie",
	"shared":     "Shared",
}

// Placed is a rule whose type lives in one package but which belongs to a language's namespace, as a shared rule bound by that language does.
type Placed interface {
	Language() any
}

func ClassOf(kind Kind, rule any) string {
	declared := reflect.TypeOf(rule)
	for declared.Kind() == reflect.Pointer {
		declared = declared.Elem()
	}

	home := declared.PkgPath()
	if placed, isPlaced := rule.(Placed); isPlaced {
		home = reflect.TypeOf(placed.Language()).PkgPath()
	}
	folders := strings.Split(home, "/")
	var path []string

	for _, folder := range folders[indexOfKind(folders)+1:] {
		switch {
		case folder == "typescript" && kind == Skill:
			path = append(path, "TypeScript")
		case folder == "typescript":
			path = append(path, "Frontend", "TypeScript")
		default:
			path = append(path, namespaces[folder])
		}
	}

	return phpToolClassNamespace + string(kind) + `\` + strings.Join(append(path, catalog.Name(rule)), `\`)
}

func indexOfKind(folders []string) int {
	for i, folder := range folders {
		if folder == "sins" || folder == "skill" || folder == "detectors" {
			return i
		}
	}

	return len(folders) - 1
}

func InClassOrder[R any](kind Kind, rules []R) []R {
	ordered := slices.Clone(rules)

	slices.SortStableFunc(ordered, func(a, b R) int {
		return strings.Compare(ClassOf(kind, a), ClassOf(kind, b))
	})

	return ordered
}
