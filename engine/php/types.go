package php

import (
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

var functionLikes = []string{"Stmt_ClassMethod", "Stmt_Function", "Expr_Closure", "Expr_ArrowFunction", "PropertyHook"}

// Types resolves the class an expression holds, as far as the program declares it: a variable through the assignments and parameters of its function, a construction, a static call through its declared return, a property or a method send through the receiver's declarations.
type Types struct {
	program    *Program
	fieldType  map[string]map[string]held
	returnType map[string]map[string]held
	nullable   map[string]map[string][]bool
	paramType  map[string]map[string][]string
	variadic   map[string]map[string]bool
	element    map[string]map[string]*contract.Type
	parentOf   map[string]string
	traitsOf   map[string][]string

	mutex  sync.Mutex
	locals map[scope]map[string]held
}

// held is the type an expression holds: the class every reader of the type engine asks for, and the written type the engine read that class off, where a declaration wrote one.
type held struct {
	class   string
	written *contract.Type
}

func typed(written TypeName) held {
	return held{class: typeName(written), written: written.written}
}

var resolvers = engine.Memoised(indexTypes)

func TypesOf(codebase *engine.Codebase) *Types {
	return resolvers.Of(codebase)
}

func indexTypes(codebase *engine.Codebase) *Types {
	types := &Types{
		program:    ProgramOf(codebase),
		fieldType:  map[string]map[string]held{},
		returnType: map[string]map[string]held{},
		nullable:   map[string]map[string][]bool{},
		paramType:  map[string]map[string][]string{},
		variadic:   map[string]map[string]bool{},
		element:    map[string]map[string]*contract.Type{},
		parentOf:   map[string]string{},
		traitsOf:   map[string][]string{},
		locals:     map[scope]map[string]held{},
	}
	for _, file := range codebase.Of(contract.PHP).Files() {
		for _, node := range file.Nodes() {
			if slices.Contains(classLikes, node.Kind) && node.Symbol != "" {
				types.index(file.Match(node.ID))
			}
		}
	}
	if program, wrote := codebase.Program(contract.PHP); wrote {
		for _, symbol := range program.Symbols {
			types.indexOutside(symbol)
		}
	}

	return types
}

func (t *Types) indexOutside(symbol contract.OutsideSymbol) {
	class := symbol.Symbol
	if _, scanned := t.program.Declaration(class); scanned {
		return
	}
	if len(symbol.Extends) > 0 && symbol.Kind == "class" {
		t.parentOf[class] = symbol.Extends[0]
	}
	t.traitsOf[class] = append(t.traitsOf[class], symbol.Uses...)
	for _, member := range symbol.Members {
		switch member.Kind {
		case "method":
			returns := member.Returns
			if returns == nil {
				returns = member.Documented
			}
			set(t.returnType, class, member.Name, typed(Written(returns)))
			var nullable []bool
			var written []string
			variadic := false
			for _, parameter := range member.Parameters {
				declared := Written(parameter.Declared)
				nullable = append(nullable, parameter.Declared == nil || declared.IsNullable())
				written = append(written, declared.SimpleName())
				variadic = variadic || slices.Contains(parameter.Flags, "variadic")
			}
			set(t.nullable, class, member.Name, nullable)
			set(t.paramType, class, member.Name, written)
			set(t.variadic, class, member.Name, variadic)
		case "property":
			set(t.fieldType, class, member.Name, typed(Written(member.Declared)))
		}
	}
}

func (t *Types) index(declaration engine.Match) {
	node := declaration.Node()
	class := node.Symbol
	if parent := child(node, "extends"); node.Kind == "Stmt_Class" && parent.Name != "" {
		t.parentOf[class] = parent.Name
	}
	t.traitsOf[class] = append(t.traitsOf[class], traitsOf(node)...)
	constructorDoc := ""
	if constructor, ok := Method(declaration, "__construct"); ok && node.Kind == "Stmt_Class" {
		if doc, ok := DocComment(constructor); ok {
			constructorDoc = doc.Text
		}
	}
	for _, param := range ConstructorParams(declaration) {
		if !slices.Contains(param.Node().Flags, "promoted") {
			continue
		}
		field := param.Name()
		set(t.fieldType, class, field, typed(Written(param.Node().Declared)))
		t.recordCollectionElement(class, field, param)
		doc := constructorDoc
		if own, ok := DocComment(param); ok {
			doc = own.Text
		}
		t.recordDocumentedElement(class, field, doc, field, param.Source())
	}
	for _, member := range declaration.Children() {
		if member.Kind() != "Stmt_Property" {
			continue
		}
		doc := ""
		if own, ok := DocComment(member); ok {
			doc = own.Text
		}
		for _, item := range member.Children() {
			if item.Node().Field != "props" {
				continue
			}
			set(t.fieldType, class, item.Name(), typed(Written(member.Node().Declared)))
			t.recordCollectionElement(class, item.Name(), member)
			t.recordDocumentedElement(class, item.Name(), doc, "", member.Source())
		}
	}
	for _, method := range Methods(declaration) {
		name := method.Name()
		set(t.returnType, class, name, typed(Written(method.Node().Returns)))
		var nullable []bool
		var written []string
		variadic := false
		for _, param := range Params(method) {
			nullable = append(nullable, acceptsNull(param))
			written = append(written, Written(param.Node().Declared).SimpleName())
			variadic = variadic || slices.Contains(param.Node().Flags, "variadic")
		}
		set(t.nullable, class, name, nullable)
		set(t.paramType, class, name, written)
		set(t.variadic, class, name, variadic)
	}
}

func (t *Types) recordCollectionElement(class, field string, owner engine.Match) {
	for _, group := range owner.Children() {
		if group.Kind() != "AttributeGroup" {
			continue
		}
		for _, attribute := range group.Children() {
			if !strings.HasSuffix(attribute.Child("name").Name(), "DataCollectionOf") {
				continue
			}
			element := classNamedBy(firstArgument(attribute))
			if element == "" {
				continue
			}
			set(t.element, class, field, elementType(element, "attribute"))
		}
	}
}

func elementType(class, origin string) *contract.Type {
	return &contract.Type{Text: `\` + class, Kind: "named", Name: class, Origin: origin}
}

func firstArgument(attribute engine.Match) engine.Match {
	for _, arg := range attribute.Children() {
		if arg.Node().Field != "args" {
			continue
		}

		return arg.Child("value")
	}

	return engine.Match{}
}

func classNamedBy(argument engine.Match) string {
	switch argument.Kind() {
	case "Expr_ClassConstFetch":
		named := argument.Child("class")
		if !isName(named) {
			return ""
		}

		return strings.TrimLeft(named.Name(), `\`)
	case "Scalar_String":
		value, isText := argument.Node().Value.Text()
		if !isText {
			return ""
		}

		return strings.TrimLeft(value, `\`)
	}

	return ""
}

func (t *Types) recordDocumentedElement(class, field, doc, variable string, file *engine.File) {
	if _, ok := t.element[class][field]; ok {
		return
	}
	if written := ElementNamed(doc, variable); written != "" {
		set(t.element, class, field, elementType(Resolve(written, file), "docblock"))
	}
}

func (t *Types) TypeOf(expr engine.Match) string {
	function := enclosingFunction(expr)
	if !function.Exists() {
		return ""
	}

	return t.TypeIn(expr, function, EnclosingClassName(expr))
}

func (t *Types) TypeIn(expr, function engine.Match, self string) string {
	if !function.Exists() {
		return ""
	}

	return t.resolve(expr, t.localTypes(function, self), self).class
}

// scope is a function read as though it sat in a class: `$this` is that class.
type scope struct {
	function *contract.Node
	self     string
}

func init() {
	engine.Fills(contract.PHP, func(codebase *engine.Codebase, changed []string) {
		TypesOf(codebase).fill(codebase, changed)
	})
}

func (t *Types) Fill(codebase *engine.Codebase) {
	t.fill(codebase, nil)
}

func (t *Types) fill(codebase *engine.Codebase, changed []string) {
	for _, file := range codebase.Of(contract.PHP).Files() {
		if len(changed) > 0 && !slices.Contains(changed, file.File.Path) {
			continue
		}

		for _, node := range file.Nodes() {
			if node.Role != "expression" {
				continue
			}
			expression := file.Match(node.ID)
			if resolved := t.heldBy(expression); resolved.class != "" {
				node.Resolved = resolvedType(resolved)
			}
			t.fillTarget(expression)
		}
	}
	engine.Untouched()
}

func (t *Types) heldBy(expr engine.Match) held {
	function := enclosingFunction(expr)
	if !function.Exists() {
		return held{}
	}

	return t.resolve(expr, t.localTypes(function, EnclosingClassName(expr)), EnclosingClassName(expr))
}

func resolvedType(resolved held) *contract.Type {
	if named := Written(resolved.written).Named(); named != nil && strings.TrimLeft(named.Name, `\`) == resolved.class {
		return named
	}

	if slices.Contains(specialClassNames, strings.ToLower(resolved.class)) {
		return &contract.Type{Text: resolved.class, Kind: "keyword", Name: resolved.class, Origin: "inferred"}
	}

	return &contract.Type{Text: `\` + resolved.class, Kind: "named", Name: resolved.class, Origin: "inferred"}
}

func (t *Types) DeclaringClassOf(fqcn, field string) string {
	for _, class := range t.ancestry(fqcn) {
		if _, ok := t.fieldType[class][field]; ok {
			return class
		}
	}

	return ""
}

func (t *Types) DeclaringClassOfMethod(fqcn, method string) string {
	for _, class := range t.ancestry(fqcn) {
		if owner := t.methodDeclaredBy(class, method, map[string]bool{}); owner != "" {
			return owner
		}
	}

	return ""
}

func (t *Types) PropertyTypeOf(fqcn, field string) string {
	return t.fieldType[t.DeclaringClassOf(fqcn, field)][field].class
}

func (t *Types) CollectionElementOf(fqcn, field string) string {
	if element := t.element[t.DeclaringClassOf(fqcn, field)][field]; element != nil {
		return element.Name
	}

	return ""
}

func (t *Types) MethodIsVariadic(fqcn, method string) bool {
	return t.variadic[t.DeclaringClassOfMethod(fqcn, method)][method]
}

func (t *Types) ParamTypeOf(fqcn, method string, position int) string {
	written := t.paramType[t.DeclaringClassOfMethod(fqcn, method)][method]
	if position >= len(written) {
		return ""
	}

	return written[position]
}

func (t *Types) ParamIsNullable(fqcn, method string, position int) (nullable, known bool) {
	accepts := t.nullable[strings.TrimLeft(fqcn, `\`)][method]
	if position >= len(accepts) {
		return false, false
	}

	return accepts[position], true
}

func (t *Types) ancestry(fqcn string) []string {
	class := strings.TrimLeft(fqcn, `\`)
	var chain []string
	for class != "" && !slices.Contains(chain, class) {
		chain = append(chain, class)
		class = t.parentOf[class]
	}

	return chain
}

func (t *Types) methodDeclaredBy(class, method string, seen map[string]bool) string {
	if seen[class] {
		return ""
	}
	seen[class] = true
	if _, ok := t.returnType[class][method]; ok {
		return class
	}
	for _, trait := range t.traitsOf[class] {
		if owner := t.methodDeclaredBy(trait, method, seen); owner != "" {
			return owner
		}
	}

	return ""
}

func (t *Types) resolve(expr engine.Match, locals map[string]held, self string) held {
	switch expr.Kind() {
	case "Expr_Variable":
		if expr.Name() == "" {
			return held{}
		}
		if expr.Name() == "this" {
			return held{class: self}
		}

		return locals[expr.Name()]
	case "Expr_New":
		if class := expr.Child("class"); isName(class) {
			return held{class: strings.TrimLeft(class.Name(), `\`)}
		}
	case "Expr_StaticCall":
		class, method := expr.Child("class"), expr.Child("name")
		if !isName(class) || method.Kind() != "Identifier" {
			return held{}
		}
		owner := strings.TrimLeft(class.Name(), `\`)
		returned := t.returnType[owner][method.Name()]
		if isSelfReferential(returned.class) || (returned.class == "" && strings.HasPrefix(method.Name(), "from")) {
			return held{class: owner}
		}

		return returned
	case "Expr_PropertyFetch":
		name := expr.Child("name")
		if name.Kind() != "Identifier" {
			return held{}
		}
		receiver := t.resolve(expr.Child("var"), locals, self)
		if receiver.class == "" {
			return held{}
		}

		return t.fieldType[t.DeclaringClassOf(receiver.class, name.Name())][name.Name()]
	case "Expr_MethodCall", "Expr_NullsafeMethodCall":
		name := expr.Child("name")
		if name.Kind() != "Identifier" {
			return held{}
		}
		receiver := t.resolve(expr.Child("var"), locals, self)
		if receiver.class == "" {
			return held{}
		}
		returned := t.returnType[receiver.class][name.Name()]
		if isSelfReferential(returned.class) {
			return held{class: receiver.class}
		}

		return returned
	case "Expr_BinaryOp_Coalesce":
		if left := t.resolve(expr.Child("left"), locals, self); left.class != "" {
			return left
		}

		return t.resolve(expr.Child("right"), locals, self)
	case "Expr_Ternary":
		chosen := expr.Child("if")
		if !chosen.Exists() {
			chosen = expr.Child("cond")
		}
		if resolved := t.resolve(chosen, locals, self); resolved.class != "" {
			return resolved
		}

		return t.resolve(expr.Child("else"), locals, self)
	}

	return held{}
}

func (t *Types) ElementOf(access engine.Match) *contract.Type {
	name := access.Child("name")
	if !slices.Contains([]string{"Expr_PropertyFetch", "Expr_NullsafePropertyFetch"}, access.Kind()) || name.Kind() != "Identifier" {
		return nil
	}

	held := t.heldBy(access.Child("var"))
	if held.class == "" {
		return nil
	}

	return t.element[t.DeclaringClassOf(held.class, name.Name())][name.Name()]
}

func (t *Types) localTypes(function engine.Match, self string) map[string]held {
	t.mutex.Lock()
	cached, ok := t.locals[scope{function.Node(), self}]
	t.mutex.Unlock()
	if ok {
		return cached
	}
	locals := t.capturedTypes(function, self)
	for _, param := range Params(function) {
		if variable := param.Child("var"); variable.Kind() == "Expr_Variable" && variable.Name() != "" {
			locals[variable.Name()] = typed(Written(param.Node().Declared))
		}
	}
	descendants := descendantsOf(function)
	for _, node := range descendants {
		if node.Kind() != "Expr_Assign" {
			continue
		}
		if variable := node.Child("var"); variable.Kind() == "Expr_Variable" && variable.Name() != "" {
			locals[variable.Name()] = t.resolve(node.Child("expr"), locals, self)
		}
	}
	for _, node := range descendants {
		if node.Kind() != "Stmt_Foreach" {
			continue
		}
		value, collection := node.Child("valueVar"), node.Child("expr")
		if value.Kind() != "Expr_Variable" || value.Name() == "" || collection.Kind() != "Expr_PropertyFetch" || collection.Child("name").Kind() != "Identifier" {
			continue
		}
		owner := t.resolve(collection.Child("var"), locals, self)
		if element := t.element[owner.class][collection.Child("name").Name()]; owner.class != "" && element != nil {
			locals[value.Name()] = held{class: element.Name}
		}
	}
	t.mutex.Lock()
	defer t.mutex.Unlock()
	if cached, ok := t.locals[scope{function.Node(), self}]; ok {
		return cached
	}
	t.locals[scope{function.Node(), self}] = locals

	return locals
}

func (t *Types) capturedTypes(function engine.Match, self string) map[string]held {
	captured := map[string]held{}
	enclosing := enclosingFunction(function)
	if !enclosing.Exists() {
		return captured
	}
	outer := t.localTypes(enclosing, self)
	switch function.Kind() {
	case "Expr_ArrowFunction":
		for name, class := range outer {
			captured[name] = class
		}
	case "Expr_Closure":
		for _, use := range function.Children() {
			if variable := use.Child("var"); use.Kind() == "ClosureUse" && variable.Kind() == "Expr_Variable" && variable.Name() != "" {
				captured[variable.Name()] = outer[variable.Name()]
			}
		}
	}

	return captured
}

func typeName(written TypeName) string {
	switch {
	case written.written == nil:
		return ""
	case written.isSugared():
		return typeName(Written(written.bare()))
	case written.isUnion():
		if sole := written.soleNonNullMember(); sole != nil {
			return typeName(*sole)
		}

		return ""
	case written.isWrittenAsName():
		return strings.TrimLeft(written.written.Name, `\`)
	}

	return ""
}

func acceptsNull(param engine.Match) bool {
	declared := Written(param.Node().Declared)

	return declared.written == nil || declared.isSugared() ||
		(declared.written.Kind == "keyword" && strings.ToLower(declared.written.Name) == "mixed") ||
		declared.isNullableUnion() || IsNullConstant(param.Child("default"))
}

func IsNullConstant(node engine.Match) bool {
	return node.Exists() && node.Node().Literal == "null"
}

func isSelfReferential(class string) bool {
	return class == "static" || class == "self"
}

func isName(node engine.Match) bool {
	return strings.HasPrefix(node.Kind(), "Name")
}

func set[V any](index map[string]map[string]V, class, member string, value V) {
	if index[class] == nil {
		index[class] = map[string]V{}
	}
	index[class][member] = value
}

func Arguments(call engine.Match) []engine.Match {
	var arguments []engine.Match
	for _, argument := range call.Children() {
		if argument.Node().Field == "args" && argument.Kind() == "Arg" {
			arguments = append(arguments, argument)
		}
	}

	return arguments
}

func Params(function engine.Match) []engine.Match {
	var params []engine.Match
	for _, param := range function.Children() {
		if param.Node().Field == "params" {
			params = append(params, param)
		}
	}

	return params
}

func enclosingFunction(node engine.Match) engine.Match {
	for at := node.Parent(); at.Exists(); at = at.Parent() {
		if slices.Contains(functionLikes, at.Kind()) {
			return at
		}
	}

	return engine.Match{}
}

func EnclosingFunctionName(node engine.Match) string {
	for at := node; at.Exists(); at = at.Parent() {
		if at.Kind() == "Stmt_ClassMethod" || at.Kind() == "Stmt_Function" {
			return at.Name()
		}
	}

	return ""
}

func EnclosingClassName(node engine.Match) string {
	for at := node; at.Exists(); at = at.Parent() {
		if slices.Contains(classLikes, at.Kind()) {
			return at.Node().Symbol
		}
	}

	return ""
}

func descendantsOf(node engine.Match) []engine.Match {
	var all []engine.Match
	for _, child := range node.Children() {
		all = append(all, child)
		all = append(all, descendantsOf(child)...)
	}

	return all
}
