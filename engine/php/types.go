package php

import (
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// functionLikes are the kinds that open a scope of their own.
var functionLikes = []string{"Stmt_ClassMethod", "Stmt_Function", "Expr_Closure", "Expr_ArrowFunction", "PropertyHook"}

// Types resolves the class an expression holds, as far as the program declares it: a variable through the
// assignments and parameters of its function, a construction, a static call through its declared return, a
// property or a method send through the receiver's declarations. An answer is a class name, or `static`/`self`
// where the source says only that; empty where nothing can be said.
//
// Its index keys each class's fields, methods and parameters exactly as written, and a class two files declare
// holds the members of both.
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

// held is the type an expression holds: the class every reader of the type engine asks for, and the written type
// the engine read that class off, where a declaration wrote one. Keeping the written type is what lets the fill
// say a value admits null, what shape its type has and where the type came from, rather than naming a class and
// calling the answer its own.
type held struct {
	class   string
	written *contract.Type
}

// typed is the class a written type names, with that type kept beside it.
func typed(written TypeName) held {
	return held{class: typeName(written), written: written.written}
}

var resolvers = Memoised(indexTypes)

// TypesOf is the codebase's type resolver, indexed on first need and kept for the codebase's life.
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

// indexOutside records what a declaration outside the scan says of its members, as the project's autoloader let the
// bridge read it: each method's return type, written or documented by an @method tag as a facade's are, its
// parameters' types, and each property's type. A declaration the scan holds keeps what the scan says.
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
		// One property statement may declare several properties, and each of them is its own field.
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

// recordCollectionElement records the element a field's `#[DataCollectionOf(X::class)]` declares; the last one
// written wins.
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

// elementType is the element a collection is declared to hold, as the contract carries one: a class named by the
// declaration that said so, which is the only thing that says what a PHP array holds.
func elementType(class, origin string) *contract.Type {
	return &contract.Type{Text: `\` + class, Kind: "named", Name: class, Origin: origin}
}

// firstArgument is the value an attribute is handed first; no node for an attribute handed nothing.
func firstArgument(attribute engine.Match) engine.Match {
	for _, arg := range attribute.Children() {
		if arg.Node().Field != "args" {
			continue
		}

		return arg.Child("value")
	}

	return engine.Match{}
}

// classNamedBy is the class an attribute's argument names, written either as `X::class` or as the string of its
// name; empty for an argument that names no class, and for one PHP could not read a name out of.
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

// recordDocumentedElement records the element a field's docblock declares, unless an attribute already has.
func (t *Types) recordDocumentedElement(class, field, doc, variable string, file *engine.File) {
	if _, ok := t.element[class][field]; ok {
		return
	}
	if written := ElementNamed(doc, variable); written != "" {
		set(t.element, class, field, elementType(Resolve(written, file), "docblock"))
	}
}

// TypeOf is the class the expression holds, read in its enclosing function and class.
func (t *Types) TypeOf(expr engine.Match) string {
	function := enclosingFunction(expr)
	if !function.Exists() {
		return ""
	}

	return t.TypeIn(expr, function, EnclosingClassName(expr))
}

// TypeIn is the class the expression holds, read in the function as though it sat in the class. Empty without a
// function.
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

// Fill writes what the engine fills for PHP: the `resolved` TypeOf answers for every expression inside a function,
// and the `target` Callee finds for every call and construction.
func (t *Types) Fill(codebase *engine.Codebase) {
	for _, file := range codebase.Of(contract.PHP).Files() {
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

// heldBy is the type the expression holds, with the written type the engine read it off where there is one.
func (t *Types) heldBy(expr engine.Match) held {
	function := enclosingFunction(expr)
	if !function.Exists() {
		return held{}
	}

	return t.resolve(expr, t.localTypes(function, EnclosingClassName(expr)), EnclosingClassName(expr))
}

// resolvedType is the type the engine fills on an expression. Where a declaration wrote the type the class was
// read off, that type IS the answer: its nullability, its shape and the origin that says whether a human wrote it
// or a docblock claimed it all survive, and nothing is parsed back out of a name. Otherwise the engine worked the
// class out itself — a construction, a `static` return — and says so.
func resolvedType(resolved held) *contract.Type {
	if named := Written(resolved.written).Named(); named != nil && strings.TrimLeft(named.Name, `\`) == resolved.class {
		return named
	}

	if slices.Contains(specialClassNames, strings.ToLower(resolved.class)) {
		return &contract.Type{Text: resolved.class, Kind: "keyword", Name: resolved.class, Origin: "inferred"}
	}

	return &contract.Type{Text: `\` + resolved.class, Kind: "named", Name: resolved.class, Origin: "inferred"}
}

// DeclaringClassOf is the class in the chain from fqcn up that declares the field.
func (t *Types) DeclaringClassOf(fqcn, field string) string {
	for _, class := range t.ancestry(fqcn) {
		if _, ok := t.fieldType[class][field]; ok {
			return class
		}
	}

	return ""
}

// DeclaringClassOfMethod is the class in the chain from fqcn up that declares the method, itself or through a trait.
func (t *Types) DeclaringClassOfMethod(fqcn, method string) string {
	for _, class := range t.ancestry(fqcn) {
		if owner := t.methodDeclaredBy(class, method, map[string]bool{}); owner != "" {
			return owner
		}
	}

	return ""
}

// PropertyTypeOf is the class the field is declared to hold.
func (t *Types) PropertyTypeOf(fqcn, field string) string {
	return t.fieldType[t.DeclaringClassOf(fqcn, field)][field].class
}

// CollectionElementOf is the element class the field's collection is declared to hold.
func (t *Types) CollectionElementOf(fqcn, field string) string {
	if element := t.element[t.DeclaringClassOf(fqcn, field)][field]; element != nil {
		return element.Name
	}

	return ""
}

// MethodIsVariadic says whether the method takes a variadic parameter.
func (t *Types) MethodIsVariadic(fqcn, method string) bool {
	return t.variadic[t.DeclaringClassOfMethod(fqcn, method)][method]
}

// ParamTypeOf is the type the method's parameter at the position is written with: a class or a builtin.
func (t *Types) ParamTypeOf(fqcn, method string, position int) string {
	written := t.paramType[t.DeclaringClassOfMethod(fqcn, method)][method]
	if position >= len(written) {
		return ""
	}

	return written[position]
}

// ParamIsNullable says whether the parameter at the position accepts null, when the class itself declares the
// method; unknown otherwise.
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

// resolve is the type an expression holds, read through the function's locals and the class around it. It answers
// the written type beside the class wherever a declaration wrote one, and nothing at all where nothing can be said.
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

// ElementOf is the element the collection a member access reads is declared to hold: the class a docblock
// `list<X>` or a `#[DataCollectionOf(X::class)]` names, which is the only thing that says what a PHP array holds,
// since the type itself says `array` and no more. No type for anything else, and none where no declaration says.
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

// localTypes is the class each variable of a function holds: what it captures, its parameters, then each
// assignment in the order written, nested functions' included, the last one winning; then a `foreach` over a typed
// collection field types its value. Read once per function and class.
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

// capturedTypes is what a function inherits from the one around it: an arrow function every variable, a closure the
// ones it names in `use`.
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

// typeName is the class a written type names, null aside, as written: `self` and `static` too.
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

// acceptsNull says whether a parameter takes null: untyped, nullable, mixed, or defaulting to null.
func acceptsNull(param engine.Match) bool {
	declared := Written(param.Node().Declared)

	return declared.written == nil || declared.isSugared() ||
		(declared.written.Kind == "keyword" && strings.ToLower(declared.written.Name) == "mixed") ||
		declared.isNullableUnion() || IsNullConstant(param.Child("default"))
}

// IsNullConstant says whether the node is the constant null.
func IsNullConstant(node engine.Match) bool {
	return node.Exists() && node.Node().Literal == "null"
}

func isSelfReferential(class string) bool {
	return class == "static" || class == "self"
}

// isName says whether a node is a name as php-parser writes one: relative, qualified or fully qualified.
func isName(node engine.Match) bool {
	return strings.HasPrefix(node.Kind(), "Name")
}

func set[V any](index map[string]map[string]V, class, member string, value V) {
	if index[class] == nil {
		index[class] = map[string]V{}
	}
	index[class][member] = value
}

// Arguments is each argument a call passes, in order; a `...` placeholder passes none.
func Arguments(call engine.Match) []engine.Match {
	var arguments []engine.Match
	for _, argument := range call.Children() {
		if argument.Node().Field == "args" && argument.Kind() == "Arg" {
			arguments = append(arguments, argument)
		}
	}

	return arguments
}

// Params is each parameter a function-like declares, in order.
func Params(function engine.Match) []engine.Match {
	var params []engine.Match
	for _, param := range function.Children() {
		if param.Node().Field == "params" {
			params = append(params, param)
		}
	}

	return params
}

// enclosingFunction is the nearest function-like around the node, the node itself aside.
func enclosingFunction(node engine.Match) engine.Match {
	for at := node.Parent(); at.Exists(); at = at.Parent() {
		if slices.Contains(functionLikes, at.Kind()) {
			return at
		}
	}

	return engine.Match{}
}

// EnclosingFunctionName is the name of the method or function around the node, or of the node itself; a closure
// or arrow function passes it over. Empty outside any.
func EnclosingFunctionName(node engine.Match) string {
	for at := node; at.Exists(); at = at.Parent() {
		if at.Kind() == "Stmt_ClassMethod" || at.Kind() == "Stmt_Function" {
			return at.Name()
		}
	}

	return ""
}

// EnclosingClassName is the name of the nearest class-like around the node, or of the node itself; empty inside an
// anonymous class.
func EnclosingClassName(node engine.Match) string {
	for at := node; at.Exists(); at = at.Parent() {
		if slices.Contains(classLikes, at.Kind()) {
			return at.Node().Symbol
		}
	}

	return ""
}

// descendantsOf is every node under the node, in pre-order.
func descendantsOf(node engine.Match) []engine.Match {
	var all []engine.Match
	for _, child := range node.Children() {
		all = append(all, child)
		all = append(all, descendantsOf(child)...)
	}

	return all
}
