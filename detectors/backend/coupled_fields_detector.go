package backend

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// CoupledFieldsDetector finds a class holding fields that belong together as a value of their own: assembled as a
// group again and again, reached through a peer field, or mirrored beside the object that already holds them.
type CoupledFieldsDetector struct{}

func init() { detectors.Register(catalog.Backend, CoupledFieldsDetector{}) }

// Sin is the sin the detector finds.
func (CoupledFieldsDetector) Sin() sins.Sin { return backendsins.CoupledFields{} }

// Find is every class of two or more fields some of which are coupled.
func (CoupledFieldsDetector) Find(codebase *engine.Codebase) []engine.Match {
	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(func(n php.Node) bool { return isCoupled(codebase, n) })).
		Get()
}

func isCoupled(codebase *engine.Codebase, class php.Node) bool {
	fields := php.Fields(class.Match)
	if len(fields) < 2 {
		return false
	}
	program := php.ProgramOf(codebase)
	values := map[string]bool{}
	for _, field := range fields {
		if program.IsValueType(field.Type) {
			values[field.Name] = true
		}
	}

	return coupledValues(class, values, len(fields)) || crossObjectClump(codebase, class) || redundantMirror(codebase, class, fields)
}

// coupledValues says whether two or more value fields are assembled together in two places, or guarded for
// absence together in one.
func coupledValues(class php.Node, values map[string]bool, fieldCount int) bool {
	if len(values) < 2 {
		return false
	}
	tested := class.SelfPropertiesTestedForAbsence(spatie.Optional)
	occurrences := map[string]int{}
	for _, assembled := range class.SelfPropertyGroupsAssembled() {
		var group []string
		for _, name := range assembled {
			if values[name] {
				group = append(group, name)
			}
		}
		if len(group) < 2 || len(group) >= fieldCount {
			continue
		}
		guarded := 0
		for _, name := range group {
			if slices.Contains(tested, name) {
				guarded++
			}
		}
		if guarded >= 2 && guarded*2 >= len(group) {
			return true
		}
		slices.Sort(group)
		key := strings.Join(group, ",")
		occurrences[key]++
		if occurrences[key] >= 2 {
			return true
		}
	}

	return false
}

// crossObjectClump says whether two or more times a field is used beside a declared class's field of the same type
// reached through another field.
func crossObjectClump(codebase *engine.Codebase, class php.Node) bool {
	name := php.EnclosingClassName(class.Match)
	if name == "" {
		return false
	}
	types, program := php.TypesOf(codebase), php.ProgramOf(codebase)
	peers := 0
	for _, triple := range class.SelfFieldNestedReachTriples() {
		direct := types.PropertyTypeOf(name, triple[0])
		base := types.PropertyTypeOf(name, triple[1])
		reached := ""
		if base != "" {
			reached = types.PropertyTypeOf(base, triple[2])
		}
		if direct == "" || reached == "" || strings.TrimLeft(direct, `\`) != strings.TrimLeft(reached, `\`) {
			continue
		}
		if _, declared := program.Declaration(direct); !declared {
			continue
		}
		peers++
		if peers >= 2 {
			return true
		}
	}

	return false
}

// redundantMirror says whether a field copies a public value field of an object another field already holds:
// order and orderTotal beside order->total.
func redundantMirror(codebase *engine.Codebase, class php.Node, fields []php.Field) bool {
	program := php.ProgramOf(codebase)
	for _, object := range fields {
		written := php.Written(object.Type)
		held := written.Class()
		if held == "" {
			held = written.NullableClass()
		}
		declaration, declared := program.Declaration(held)
		if held == "" || !declared {
			continue
		}
		for _, inner := range php.Fields(declaration) {
			if inner.IsPublic && program.IsValueType(inner.Type) && mirroredBy(class, fields, object, inner) {
				return true
			}
		}
	}

	return false
}

func mirroredBy(class php.Node, fields []php.Field, object, inner php.Field) bool {
	for _, mirror := range fields {
		if mirror.Name == object.Name {
			continue
		}
		rendered := php.Written(mirror.Type).Render()
		if strings.EqualFold(mirror.Name, object.Name+upperFirst(inner.Name)) && rendered == php.Written(inner.Type).Render() &&
			rendered != "" && !class.RewritesSelfPropertyOutsideConstructor(mirror.Name) {
			return true
		}
	}

	return false
}

func upperFirst(name string) string {
	if name == "" || name[0] < 'a' || name[0] > 'z' {
		return name
	}

	return string(name[0]-'a'+'A') + name[1:]
}

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (CoupledFieldsDetector) CrossFile() {}
