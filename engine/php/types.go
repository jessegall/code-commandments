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
	fieldType  map[string]map[string]string
	returnType map[string]map[string]string
	nullable   map[string]map[string][]bool
	paramType  map[string]map[string][]string
	variadic   map[string]map[string]bool
	element    map[string]map[string]string
	parentOf   map[string]string
	traitsOf   map[string][]string

	mutex  sync.Mutex
	locals map[scope]map[string]string
}

var resolvers = Memoised(indexTypes)

// TypesOf is the codebase's type resolver, indexed on first need and kept for the codebase's life.
func TypesOf(codebase *engine.Codebase) *Types {
	return resolvers.Of(codebase)
}

func indexTypes(codebase *engine.Codebase) *Types {
	types := &Types{
		program:    ProgramOf(codebase),
		fieldType:  map[string]map[string]string{},
		returnType: map[string]map[string]string{},
		nullable:   map[string]map[string][]bool{},
		paramType:  map[string]map[string][]string{},
		variadic:   map[string]map[string]bool{},
		element:    map[string]map[string]string{},
		parentOf:   map[string]string{},
		traitsOf:   map[string][]string{},
		locals:     map[scope]map[string]string{},
	}
	for _, file := range codebase.Of(contract.PHP).Files() {
		for _, node := range file.Nodes() {
			if slices.Contains(classLikes, node.Kind) && node.Symbol != "" {
				types.index(file.Match(node.ID))
			}
		}
	}

	return types
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
		set(t.fieldType, class, field, typeName(Written(param.Node().Declared)))
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
			if item.Node().Field == "props" {
				set(t.fieldType, class, item.Name(), typeName(Written(member.Node().Declared)))
				t.recordCollectionElement(class, item.Name(), member)
				t.recordDocumentedElement(class, item.Name(), doc, "", member.Source())
			}
		}
	}
	for _, method := range Methods(declaration) {
		name := method.Name()
		set(t.returnType, class, name, typeName(Written(method.Node().Returns)))
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
			var first engine.Match
			for _, arg := range attribute.Children() {
				if arg.Node().Field == "args" {
					first = arg.Child("value")
					break
				}
			}
			element := ""
			switch first.Kind() {
			case "Expr_ClassConstFetch":
				if class := first.Child("class"); isName(class) {
					element = strings.TrimLeft(class.Name(), `\`)
				}
			case "Scalar_String":
				if value, ok := first.Node().Value.Text(); ok {
					element = strings.TrimLeft(value, `\`)
				}
			}
			if element != "" {
				set(t.element, class, field, element)
			}
		}
	}
}

// recordDocumentedElement records the element a field's docblock declares, unless an attribute already has.
func (t *Types) recordDocumentedElement(class, field, doc, variable string, file *engine.File) {
	if _, ok := t.element[class][field]; ok {
		return
	}
	if written := ElementNamed(doc, variable); written != "" {
		set(t.element, class, field, Resolve(written, file))
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

	return t.resolve(expr, t.localTypes(function, self), self)
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
			if class := t.TypeOf(expression); class != "" {
				node.Resolved = resolvedType(class)
			}
			t.fillTarget(expression)
		}
	}
	engine.Untouched()
}

func resolvedType(class string) *contract.Type {
	if slices.Contains(specialClassNames, strings.ToLower(class)) {
		return &contract.Type{Text: class, Kind: "keyword", Name: class, Origin: "inferred"}
	}

	return &contract.Type{Text: `\` + class, Kind: "named", Name: class, Origin: "inferred"}
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
	return t.fieldType[t.DeclaringClassOf(fqcn, field)][field]
}

// CollectionElementOf is the element class the field's collection is declared to hold.
func (t *Types) CollectionElementOf(fqcn, field string) string {
	return t.element[t.DeclaringClassOf(fqcn, field)][field]
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

func (t *Types) resolve(expr engine.Match, locals map[string]string, self string) string {
	switch expr.Kind() {
	case "Expr_Variable":
		if expr.Name() == "" {
			return ""
		}
		if expr.Name() == "this" {
			return self
		}

		return locals[expr.Name()]
	case "Expr_New":
		if class := expr.Child("class"); isName(class) {
			return strings.TrimLeft(class.Name(), `\`)
		}
	case "Expr_StaticCall":
		class, method := expr.Child("class"), expr.Child("name")
		if !isName(class) || method.Kind() != "Identifier" {
			return ""
		}
		owner := strings.TrimLeft(class.Name(), `\`)
		returned := t.returnType[owner][method.Name()]
		if isSelfReferential(returned) || (returned == "" && strings.HasPrefix(method.Name(), "from")) {
			return owner
		}

		return returned
	case "Expr_PropertyFetch":
		name := expr.Child("name")
		if name.Kind() != "Identifier" {
			return ""
		}
		receiver := t.resolve(expr.Child("var"), locals, self)
		if receiver == "" {
			return ""
		}

		return t.fieldType[t.DeclaringClassOf(receiver, name.Name())][name.Name()]
	case "Expr_MethodCall", "Expr_NullsafeMethodCall":
		name := expr.Child("name")
		if name.Kind() != "Identifier" {
			return ""
		}
		receiver := t.resolve(expr.Child("var"), locals, self)
		if receiver == "" {
			return ""
		}
		returned := t.returnType[receiver][name.Name()]
		if isSelfReferential(returned) {
			return receiver
		}

		return returned
	case "Expr_BinaryOp_Coalesce":
		if left := t.resolve(expr.Child("left"), locals, self); left != "" {
			return left
		}

		return t.resolve(expr.Child("right"), locals, self)
	case "Expr_Ternary":
		chosen := expr.Child("if")
		if !chosen.Exists() {
			chosen = expr.Child("cond")
		}
		if resolved := t.resolve(chosen, locals, self); resolved != "" {
			return resolved
		}

		return t.resolve(expr.Child("else"), locals, self)
	}

	return ""
}

// localTypes is the class each variable of a function holds: what it captures, its parameters, then each
// assignment in the order written, nested functions' included, the last one winning; then a `foreach` over a typed
// collection field types its value. Read once per function and class.
func (t *Types) localTypes(function engine.Match, self string) map[string]string {
	t.mutex.Lock()
	cached, ok := t.locals[scope{function.Node(), self}]
	t.mutex.Unlock()
	if ok {
		return cached
	}
	locals := t.capturedTypes(function, self)
	for _, param := range Params(function) {
		if variable := param.Child("var"); variable.Kind() == "Expr_Variable" && variable.Name() != "" {
			locals[variable.Name()] = typeName(Written(param.Node().Declared))
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
		if element := t.element[owner][collection.Child("name").Name()]; owner != "" && element != "" {
			locals[value.Name()] = element
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
func (t *Types) capturedTypes(function engine.Match, self string) map[string]string {
	captured := map[string]string{}
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
