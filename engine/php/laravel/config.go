package laravel

import (
	"github.com/jessegall/code-commandments/contract"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// ConfigKeys is the keys a Laravel project's own config files declare, the ones that state a default, and every
// string the project writes that could read one.
type ConfigKeys struct {
	declared map[*contract.Node]string
	literals []string
	files    map[string]bool
	defaults map[string]bool
}

var configKeys = php.Memoised(func(codebase *engine.Codebase) *ConfigKeys {
	keys := &ConfigKeys{declared: map[*contract.Node]string{}, files: map[string]bool{}, defaults: map[string]bool{}}
	var declaredKeys []string
	for _, file := range php.In(codebase).Files() {
		root := file.Match(0)
		for _, node := range root.Descendants() {
			switch node.Kind() {
			case "Scalar_String":
				text, _ := node.Text()
				keys.literals = append(keys.literals, text)
			case "Scalar_InterpolatedString":
				if parts := node.ChildrenIn("parts"); len(parts) > 0 && parts[0].Kind() == "InterpolatedStringPart" {
					text, _ := parts[0].Text()
					keys.literals = append(keys.literals, text)
				}
			}
		}
		prefix := configPrefixOf(file.Path)
		if returned := returnedArray(root); prefix != "" && returned.Exists() {
			declaredKeys = append(declaredKeys, keys.collect(returned, prefix)...)
		}
	}
	for _, key := range declaredKeys {
		file, _, _ := strings.Cut(key, ".")
		if keys.files[file] {
			continue
		}
		if slices.ContainsFunc(keys.literals, func(literal string) bool { return strings.HasPrefix(literal, file+".") }) {
			keys.files[file] = true
		}
	}

	return keys
})

// ConfigKeysOf is the codebase's config keys.
func ConfigKeysOf(codebase *engine.Codebase) *ConfigKeys {
	return configKeys.Of(codebase)
}

// DeclaresDefault says whether a config file states a default for the key.
func (k *ConfigKeys) DeclaresDefault(key string) bool {
	return key != "" && k.defaults[key]
}

// DeadKeyAt is the key the config item declares when nothing reads it, in a config file something reads from;
// empty otherwise.
func (k *ConfigKeys) DeadKeyAt(node engine.Match) string {
	key, declared := k.declared[node.Node()]
	file, _, _ := strings.Cut(key, ".")
	if !declared || !k.files[file] || k.isRead(key) {
		return ""
	}

	return key
}

func (k *ConfigKeys) isRead(key string) bool {
	return slices.ContainsFunc(k.literals, func(literal string) bool {
		return literal == key || strings.HasPrefix(literal, key+".") || strings.HasPrefix(key, literal+".")
	})
}

// configPrefixOf is the key prefix a file in a config folder declares, unless a vendor package publishes a config of
// that name.
func configPrefixOf(path string) string {
	if filepath.Base(filepath.Dir(path)) != "config" {
		return ""
	}
	name := strings.TrimSuffix(filepath.Base(path), ".php")
	root := filepath.Dir(filepath.Dir(path))
	for _, pattern := range []string{"vendor/*/*/config/", "vendor/*/*/src/config/"} {
		if published, _ := filepath.Glob(filepath.Join(root, pattern, name+".php")); len(published) > 0 {
			return ""
		}
	}

	return name
}

func returnedArray(root engine.Match) engine.Match {
	for _, statement := range root.Children() {
		if statement.Kind() == "Stmt_Return" && statement.Child("expr").Kind() == "Expr_Array" {
			return statement.Child("expr")
		}
	}

	return engine.Match{}
}

func (k *ConfigKeys) collect(array engine.Match, prefix string) []string {
	var declared []string
	for _, item := range array.ChildrenIn("items") {
		written := item.Child("key")
		if item.Kind() != "ArrayItem" || written.Kind() != "Scalar_String" {
			continue
		}
		name, _ := written.Text()
		key := prefix + "." + name
		if value := item.Child("value"); value.Kind() == "Expr_Array" {
			declared = append(declared, k.collect(value, key)...)

			continue
		}
		k.declared[item.Node()] = key
		declared = append(declared, key)
		if statesADefault(item.Child("value")) {
			k.defaults[key] = true
		}
	}

	return declared
}

func statesADefault(value engine.Match) bool {
	switch value.Kind() {
	case "Scalar_String", "Scalar_Int", "Scalar_Float":
		return true
	case "Expr_ConstFetch":
		name := strings.ToLower(value.Child("name").Name())

		return name == "true" || name == "false"
	case "Expr_FuncCall":
		return value.Child("name").Name() == "env" && strings.HasPrefix(value.Child("name").Kind(), "Name") && len(value.ChildrenIn("args")) >= 2
	}

	return false
}

// ContainerResolves says whether the container builds the class: something takes it in a constructor, or nothing
// builds it or a subclass by hand.
func ContainerResolves(codebase *engine.Codebase, class string) bool {
	if class == "" {
		return false
	}
	want := strings.TrimLeft(class, `\`)
	injected := php.In(codebase).WhereKind("Param").Where(func(m engine.Match) bool {
		return slices.Contains(php.Written(m.Node().Declared).Names(), want) && php.EnclosingFunctionName(m) == "__construct"
	}).Count() > 0
	if injected {
		return true
	}
	program := php.ProgramOf(codebase)

	return php.In(codebase).WhereKind("Expr_New").Where(func(m engine.Match) bool {
		built := php.Node{Match: m}.NewClassName()

		return built == want || program.Extends(built, want)
	}).Count() == 0
}
