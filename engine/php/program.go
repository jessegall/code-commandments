package php

import (
	"maps"
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// classLikes are the kinds that declare a type.
var classLikes = []string{"Stmt_Class", "Stmt_Interface", "Stmt_Trait", "Stmt_Enum"}

// valueScalars are the builtin types a value type may hold.
var valueScalars = []string{"string", "int", "float", "bool", "array", "iterable"}

// optionName is the short name of php-types' Option, a value whatever it wraps.
const optionName = "Option"

// Program is what the scanned PHP declares, read once per codebase: its class-likes, their parents, contracts
// and traits. Names compare exactly, as the PHP engine's maps key them.
type Program struct {
	codebase     *engine.Codebase
	declarations map[string]engine.Match
	classes      map[string]engine.Match
	parents      map[string]string
	subclassed   map[string]bool
	contracts    map[string][]string
	enums        map[string]bool
	traitUsers   map[string][]string
}

var programs sync.Map

// ProgramOf is the codebase's PHP program, read on first need and kept for the codebase's life.
func ProgramOf(codebase *engine.Codebase) *Program {
	if program, ok := programs.Load(codebase); ok {
		return program.(*Program)
	}
	program, _ := programs.LoadOrStore(codebase, readProgram(codebase))

	return program.(*Program)
}

func readProgram(codebase *engine.Codebase) *Program {
	program := &Program{
		codebase:     codebase,
		declarations: map[string]engine.Match{},
		classes:      map[string]engine.Match{},
		parents:      map[string]string{},
		subclassed:   map[string]bool{},
		contracts:    map[string][]string{},
		enums:        map[string]bool{},
		traitUsers:   map[string][]string{},
	}
	for _, file := range codebase.Of(contract.PHP).Files() {
		for _, node := range file.Nodes() {
			if slices.Contains(classLikes, node.Kind) {
				program.declare(file.Match(node.ID))
			}
		}
	}

	return program
}

func (p *Program) declare(declaration engine.Match) {
	node := declaration.Node()
	name := node.Symbol
	if node.Kind == "Stmt_Class" {
		if parent := child(node, "extends"); parent.Name != "" {
			p.subclassed[parent.Name] = true
			if name != "" {
				p.parents[name] = parent.Name
			}
		}
	}
	if name == "" {
		return
	}
	p.declarations[name] = declaration
	switch node.Kind {
	case "Stmt_Class":
		p.classes[name] = declaration
		p.contracts[name] = names(node, "implements")
	case "Stmt_Enum":
		p.enums[name] = true
		p.contracts[name] = names(node, "implements")
	case "Stmt_Interface":
		p.contracts[name] = names(node, "extends")
	}
	for _, trait := range traitsOf(node) {
		p.traitUsers[trait] = append(p.traitUsers[trait], name)
	}
}

// Declaration is the class-like the name declares: a class, interface, trait or enum.
func (p *Program) Declaration(fqcn string) (engine.Match, bool) {
	declaration, ok := p.declarations[strings.TrimLeft(fqcn, `\`)]

	return declaration, ok
}

// Declarations is every named class-like the program declares, by name.
func (p *Program) Declarations() map[string]engine.Match {
	return p.declarations
}

// Class is the class the name declares; an interface, trait or enum is no class.
func (p *Program) Class(fqcn string) (engine.Match, bool) {
	class, ok := p.classes[strings.TrimLeft(fqcn, `\`)]

	return class, ok
}

// Ancestors is the class's parent chain, nearest first, as far as the program declares it.
func (p *Program) Ancestors(class string) []string {
	class = strings.TrimLeft(class, `\`)
	var chain []string
	for {
		parent, ok := p.parents[class]
		if !ok || slices.Contains(chain, parent) {
			return chain
		}
		chain = append(chain, parent)
		class = parent
	}
}

// Extends says whether the parent is among the class's ancestors.
func (p *Program) Extends(class, parent string) bool {
	return slices.Contains(p.Ancestors(class), strings.TrimLeft(parent, `\`))
}

// Implements says whether the class honours the contract: through its own, its parents', or an interface's
// extended contracts.
func (p *Program) Implements(class, contract string) bool {
	contract = strings.TrimLeft(contract, `\`)
	queue := []string{strings.TrimLeft(class, `\`)}
	seen := map[string]bool{}
	for len(queue) > 0 {
		current := queue[len(queue)-1]
		queue = queue[:len(queue)-1]
		if seen[current] {
			continue
		}
		seen[current] = true
		for _, honoured := range p.contracts[current] {
			if honoured == contract {
				return true
			}
			queue = append(queue, honoured)
		}
		if parent, ok := p.parents[current]; ok {
			queue = append(queue, parent)
		}
	}

	return false
}

// IsA says whether the class is the base, extends it, or implements it.
func (p *Program) IsA(class, base string) bool {
	return class != "" && (class == base || p.Extends(class, base) || p.Implements(class, base))
}

// IsEnum says whether the program declares the name an enum.
func (p *Program) IsEnum(class string) bool {
	return p.enums[strings.TrimLeft(class, `\`)]
}

// IsInterface says whether the program declares the name an interface.
func (p *Program) IsInterface(class string) bool {
	declaration, ok := p.Declaration(class)

	return ok && declaration.Kind() == "Stmt_Interface"
}

// HasSubclass says whether any class, anonymous ones too, extends the class.
func (p *Program) HasSubclass(class string) bool {
	return p.subclassed[strings.TrimLeft(class, `\`)]
}

// UsersOfTrait is every class-like that uses the trait, in the order the program declares them.
func (p *Program) UsersOfTrait(trait string) []string {
	return p.traitUsers[strings.TrimLeft(trait, `\`)]
}

// TraitMethodsOf is every method the class's traits declare, trait by trait.
func (p *Program) TraitMethodsOf(class string) []engine.Match {
	declaration, ok := p.Class(class)
	if !ok {
		return nil
	}
	var methods []engine.Match
	for _, trait := range traitsOf(declaration.Node()) {
		if used, ok := p.Declaration(trait); ok {
			methods = append(methods, Methods(used)...)
		}
	}

	return methods
}

// IsValueType says whether a written type holds a value rather than a service: a scalar, an enum, an Option, or a
// declared class whose every field is a value type in turn, four levels deep. A class already on the path is a
// cycle, and no value. Null and Optional aside, a union is
// a value only when one type is left.
func (p *Program) IsValueType(written *contract.Type) bool {
	return p.isValueType(Written(written), 4, map[string]bool{})
}

// ClassIsValueType says whether the class is a value type.
func (p *Program) ClassIsValueType(fqcn string) bool {
	return fqcn != "" && p.IsValueType(&contract.Type{Kind: "named", Name: fqcn})
}

func (p *Program) isValueType(written TypeName, depth int, visited map[string]bool) bool {
	switch {
	case written.written == nil:
		return false
	case written.isSugared():
		return p.isValueType(Written(written.bare()), depth, visited)
	case written.isUnion():
		var core []TypeName
		for _, member := range written.members() {
			if !member.isNullMember() && !(member.isWrittenAsName() && lastPart(member.written.Name) == "Optional") {
				core = append(core, member)
			}
		}

		return len(core) == 1 && p.isValueType(core[0], depth, visited)
	case written.written.Kind == "keyword" && !written.isWrittenAsName():
		return slices.Contains(valueScalars, written.written.Name)
	}
	class := written.Class()
	switch {
	case class == "":
		return false
	case p.IsEnum(class) || lastPart(class) == optionName:
		return true
	}
	declaration, ok := p.Declaration(class)
	if !ok || depth <= 0 || visited[class] {
		return false
	}
	visited = maps.Clone(visited)
	visited[class] = true
	for _, field := range Fields(declaration) {
		if !p.isValueType(Written(field.Type), depth-1, visited) {
			return false
		}
	}

	return true
}

// Field is one field a class-like declares: a promoted constructor parameter, or a property.
type Field struct {
	Name     string
	Type     *contract.Type
	IsPublic bool
	Promoted bool
	Node     engine.Match
}

// Fields is every field the class-like declares, its promoted constructor parameters first, then its properties.
func Fields(declaration engine.Match) []Field {
	var fields []Field
	for _, param := range constructorParams(declaration) {
		node := param.Node()
		if !slices.Contains(node.Flags, "promoted") {
			continue
		}
		fields = append(fields, Field{Name: node.Name, Type: node.Declared, IsPublic: slices.Contains(node.Modifiers, "public"), Promoted: true, Node: param})
	}
	for _, member := range declaration.Children() {
		if member.Kind() != "Stmt_Property" {
			continue
		}
		property := member.Node()
		for _, item := range member.Children() {
			if item.Node().Field == "props" {
				fields = append(fields, Field{Name: item.Name(), Type: property.Declared, IsPublic: isPublic(property), Node: item})
			}
		}
	}

	return fields
}

// Methods is every method the class-like declares, in order.
func Methods(declaration engine.Match) []engine.Match {
	var methods []engine.Match
	for _, member := range declaration.Children() {
		if member.Kind() == "Stmt_ClassMethod" {
			methods = append(methods, member)
		}
	}

	return methods
}

// Method is the method the class-like declares by the name, compared as PHP compares method names.
func Method(declaration engine.Match, name string) (engine.Match, bool) {
	for _, method := range Methods(declaration) {
		if strings.EqualFold(method.Name(), name) {
			return method, true
		}
	}

	return engine.Match{}, false
}

func constructorParams(declaration engine.Match) []engine.Match {
	constructor, ok := Method(declaration, "__construct")
	if !ok {
		return nil
	}
	var params []engine.Match
	for _, param := range constructor.Children() {
		if param.Node().Field == "params" {
			params = append(params, param)
		}
	}

	return params
}

// isPublic says whether a member is public: written so, or written with no visibility at all.
func isPublic(member *contract.Node) bool {
	return slices.Contains(member.Modifiers, "public") ||
		!(slices.Contains(member.Modifiers, "protected") || slices.Contains(member.Modifiers, "private"))
}

func traitsOf(declaration *contract.Node) []string {
	var traits []string
	for _, member := range declaration.Children {
		if member.Kind == "Stmt_TraitUse" {
			traits = append(traits, names(member, "traits")...)
		}
	}

	return traits
}

// names is the name of each child the node holds in a list field.
func names(node *contract.Node, field string) []string {
	var names []string
	for _, child := range node.Children {
		if child.Field == field {
			names = append(names, child.Name)
		}
	}

	return names
}

func lastPart(name string) string {
	return name[strings.LastIndex(name, `\`)+1:]
}
