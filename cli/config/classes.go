package config

import (
	"reflect"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
)

// namespaces are the PHP namespace each folder of a rule's Go package stands for.
var namespaces = map[string]string{
	"backend":    "Backend",
	"frontend":   "Frontend",
	"python":     "Python",
	"csharp":     "CSharp",
	"concurrent": "Concurrent",
	"laravel":    "Laravel",
	"phptypes":   "PhpTypes",
	"spatie":     "Spatie",
}

// ClassOf is the class a config names a shipped rule by: its kind's namespace, its engine's and any package
// folder's, then its name. A TypeScript sin or detector sits under the frontend, a TypeScript skill beside it.
func ClassOf(kind Kind, rule any) string {
	folders := strings.Split(reflect.TypeOf(rule).PkgPath(), "/")
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

	return root + string(kind) + `\` + strings.Join(append(path, catalog.Name(rule)), `\`)
}

// indexOfKind is where the rule's catalog folder (sins, skill, detectors) sits in its package path.
func indexOfKind(folders []string) int {
	for i, folder := range folders {
		if folder == "sins" || folder == "skill" || folder == "detectors" {
			return i
		}
	}

	return len(folders) - 1
}

// InClassOrder is the rules in the order the PHP tool's catalogs list them: by their full class name, so a
// package folder's rules follow the engine's own.
func InClassOrder[R any](kind Kind, rules []R) []R {
	ordered := slices.Clone(rules)

	slices.SortStableFunc(ordered, func(a, b R) int {
		return strings.Compare(ClassOf(kind, a), ClassOf(kind, b))
	})

	return ordered
}
