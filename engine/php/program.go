package php

import (
	"maps"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

var classLikes = []string{"Stmt_Class", "Stmt_Interface", "Stmt_Trait", "Stmt_Enum"}

var valueScalars = []string{"string", "int", "float", "bool", "array", "iterable"}

const optionName = "Option"

// Program is what the scanned PHP declares, read once per codebase: its class-likes, their parents, contracts and traits.
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

var programs = Memoised(readProgram)

func ProgramOf(codebase *engine.Codebase) *Program {
	return programs.Of(codebase)
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

func (p *Program) Declaration(fqcn string) (engine.Match, bool) {
	declaration, ok := p.declarations[strings.TrimLeft(fqcn, `\`)]

	return declaration, ok
}

func (p *Program) Declarations() map[string]engine.Match {
	return p.declarations
}

func (p *Program) Class(fqcn string) (engine.Match, bool) {
	class, ok := p.classes[strings.TrimLeft(fqcn, `\`)]

	return class, ok
}

func (p *Program) Ancestors(class string) []string {
	class = strings.TrimLeft(class, `\`)
	var chain []string
	for {
		parent, ok := p.parentOf(class)
		if !ok || slices.Contains(chain, parent) {
			return chain
		}
		chain = append(chain, parent)
		class = parent
	}
}

func (p *Program) Extends(class, parent string) bool {
	return slices.Contains(p.Ancestors(class), strings.TrimLeft(parent, `\`))
}

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
		for _, honoured := range p.contractsOf(current) {
			if honoured == contract {
				return true
			}
			queue = append(queue, honoured)
		}
		if parent, ok := p.parentOf(current); ok {
			queue = append(queue, parent)
		}
	}

	return false
}

func (p *Program) parentOf(class string) (string, bool) {
	if parent, ok := p.parents[class]; ok {
		return parent, true
	}
	if _, declared := p.Declaration(class); declared {
		return "", false
	}
	symbol, ok := p.outside(class)
	if !ok || symbol.Kind != "class" || len(symbol.Extends) == 0 {
		return "", false
	}

	return strings.TrimLeft(symbol.Extends[0], `\`), true
}

func (p *Program) contractsOf(name string) []string {
	if _, declared := p.Declaration(name); declared {
		return p.contracts[name]
	}
	symbol, ok := p.outside(name)
	if !ok {
		return nil
	}
	if symbol.Kind == "interface" {
		return symbol.Extends
	}

	return symbol.Implements
}

func (p *Program) IsA(class, base string) bool {
	return class != "" && (class == base || p.Extends(class, base) || p.Implements(class, base))
}

func (p *Program) IsEnum(class string) bool {
	return p.enums[strings.TrimLeft(class, `\`)]
}

func (p *Program) IsInterface(class string) bool {
	declaration, ok := p.Declaration(class)

	return ok && declaration.Kind() == "Stmt_Interface"
}

func (p *Program) HasSubclass(class string) bool {
	return p.subclassed[strings.TrimLeft(class, `\`)]
}

func (p *Program) Uses(class, trait string) bool {
	users := p.UsersOfTrait(trait)
	for _, each := range append([]string{strings.TrimLeft(class, `\`)}, p.Ancestors(class)...) {
		if slices.Contains(users, each) {
			return true
		}
	}

	return false
}

func (p *Program) UsersOfTrait(trait string) []string {
	return p.traitUsers[strings.TrimLeft(trait, `\`)]
}

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

func (p *Program) IsValueType(written *contract.Type) bool {
	return p.isValueType(Written(written), 4, map[string]bool{})
}

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
	Name        string
	Type        *contract.Type
	IsPublic    bool
	Promoted    bool
	Node        engine.Match
	Declaration engine.Match
}

func (f Field) HasAttribute(shortNames ...string) bool {
	return HasAttribute(f.Declaration, shortNames...)
}

func AsField(node engine.Match) (Field, bool) {
	switch node.Kind() {
	case "Param":
		if variable := node.Child("var"); slices.Contains(node.Node().Flags, "promoted") && variable.Kind() == "Expr_Variable" && variable.Name() != "" {
			return Field{Name: variable.Name(), Type: node.Node().Declared, IsPublic: slices.Contains(node.Node().Modifiers, "public"), Promoted: true, Node: node, Declaration: node}, true
		}
	case "Stmt_Property":
		for _, item := range node.Children() {
			if item.Node().Field == "props" {
				return Field{Name: item.Name(), Type: node.Node().Declared, IsPublic: IsPublic(node.Node()), Node: item, Declaration: node}, true
			}
		}
	}

	return Field{}, false
}

func AttributeNames(declaration engine.Match) []string {
	var names []string
	for _, group := range declaration.Children() {
		if group.Kind() != "AttributeGroup" {
			continue
		}
		for _, attribute := range group.Children() {
			if attribute.Kind() == "Attribute" {
				names = append(names, attribute.Child("name").Name())
			}
		}
	}

	return names
}

func HasAttribute(declaration engine.Match, shortNames ...string) bool {
	for _, name := range AttributeNames(declaration) {
		if slices.Contains(shortNames, ShortName(name)) {
			return true
		}
	}

	return false
}

func ShortName(class string) string {
	return lastPart(class)
}

func Fields(declaration engine.Match) []Field {
	var fields []Field
	for _, param := range ConstructorParams(declaration) {
		node := param.Node()
		if !slices.Contains(node.Flags, "promoted") {
			continue
		}
		fields = append(fields, Field{Name: node.Name, Type: node.Declared, IsPublic: slices.Contains(node.Modifiers, "public"), Promoted: true, Node: param, Declaration: param})
	}
	for _, member := range declaration.Children() {
		if member.Kind() != "Stmt_Property" {
			continue
		}
		property := member.Node()
		for _, item := range member.Children() {
			if item.Node().Field == "props" {
				fields = append(fields, Field{Name: item.Name(), Type: property.Declared, IsPublic: IsPublic(property), Node: item, Declaration: member})
			}
		}
	}

	return fields
}

func Methods(declaration engine.Match) []engine.Match {
	var methods []engine.Match
	for _, member := range declaration.Children() {
		if member.Kind() == "Stmt_ClassMethod" {
			methods = append(methods, member)
		}
	}

	return methods
}

func Method(declaration engine.Match, name string) (engine.Match, bool) {
	for _, method := range Methods(declaration) {
		if strings.EqualFold(method.Name(), name) {
			return method, true
		}
	}

	return engine.Match{}, false
}

func ConstructorParams(declaration engine.Match) []engine.Match {
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

func IsPublic(member *contract.Node) bool {
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

func (p *Program) InheritsMutableProperty(class, property string) bool {
	for _, ancestor := range p.Ancestors(class) {
		declaration, ok := p.Class(ancestor)
		if ok && slices.Contains(MutablePropertyNames(declaration), property) {
			return true
		}
	}

	return false
}

func MutablePropertyNames(class engine.Match) []string {
	if class.Kind() != "Stmt_Class" {
		return nil
	}
	var names []string
	add := func(name string) {
		if !slices.Contains(names, name) {
			names = append(names, name)
		}
	}
	for _, param := range ConstructorParams(class) {
		name := variableName(param.Child("var"))
		if len(param.Node().Modifiers) > 0 && !slices.Contains(param.Node().Modifiers, "readonly") && name != "" {
			add(name)
		}
	}
	for _, property := range class.ChildrenIn("stmts") {
		if property.Kind() != "Stmt_Property" || slices.Contains(property.Node().Modifiers, "readonly") {
			continue
		}
		for _, declared := range property.ChildrenIn("props") {
			add(declared.Child("name").Name())
		}
	}

	return names
}
