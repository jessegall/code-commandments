package php

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/prose"
)

// witherCarriedFloor is how many of its own properties a rebuild must carry over to be a hand-rolled wither.
const witherCarriedFloor = 3

// CallName is the name a call is made by: a named method or static call, or a function called by name; empty for
// any other node.
func (n Node) CallName() string {
	switch n.Kind() {
	case "Expr_MethodCall", "Expr_NullsafeMethodCall", "Expr_StaticCall":
		if name := n.Child("name"); name.Kind() == "Identifier" {
			return name.Name()
		}
	case "Expr_FuncCall":
		if name := n.Child("name"); isName(name) {
			return name.Name()
		}
	}

	return ""
}

// IsPositionalTuple says whether the node is an array literal of three or more unkeyed items reading from two or
// more different variables.
func (n Node) IsPositionalTuple() bool {
	items := n.In("items")
	if n.Kind() != "Expr_Array" || len(items) < 3 {
		return false
	}
	roots := map[string]bool{}
	for _, item := range items {
		if item.Kind() != "ArrayItem" || item.Child("key").Exists() || slices.Contains(item.Node().Flags, "spread") {
			return false
		}
		if root := variableRoot(item.Child("value")); root != "" {
			roots[root] = true
		}
	}

	return len(roots) >= 2
}

// variableRoot is the variable a chain of reads and calls starts at; empty when it starts elsewhere.
func variableRoot(expr engine.Match) string {
	for {
		switch expr.Kind() {
		case "Expr_Variable":
			return expr.Name()
		case "Expr_PropertyFetch", "Expr_NullsafePropertyFetch", "Expr_MethodCall", "Expr_NullsafeMethodCall", "Expr_ArrayDimFetch":
			expr = expr.Child("var")
		default:
			return ""
		}
	}
}

// IsReturnExpression says whether the node is what a return or an arrow function returns.
func (n Node) IsReturnExpression() bool {
	parent := n.Parent()

	return n.Exists() && (parent.Kind() == "Stmt_Return" || parent.Kind() == "Expr_ArrowFunction" && n.Node().Field == "expr")
}

var sequenceReturns = []*regexp.Regexp{
	regexp.MustCompile(`@return\s+\??(non-empty-)?list\s*<`),
	regexp.MustCompile(`@return\s+\??array\s*<\s*int\s*,`),
	regexp.MustCompile(`@return\s+\??[\\\w|]+\[\]`),
}

// EnclosingFunctionReturnsSequence says whether the function around the node documents that it returns a list.
func (n Node) EnclosingFunctionReturnsSequence() bool {
	doc, ok := n.EnclosingFunctionLike().DocComment()
	if !ok {
		return false
	}
	for _, line := range prose.Lines(doc.Text) {
		if slices.ContainsFunc(sequenceReturns, func(pattern *regexp.Regexp) bool { return pattern.MatchString(line) }) {
			return true
		}
	}

	return false
}

// DecodesItsOwnEncoding says whether the call's first argument is a json_encode, casts aside.
func (n Node) DecodesItsOwnEncoding() bool {
	argument := n.Argument(0)
	for strings.HasPrefix(argument.Kind(), "Expr_Cast_") {
		argument = Node{Match: argument.Child("expr")}
	}

	return argument.Exists() && argument.CallName() == "json_encode"
}

// IsWitherRebuild says whether the node is the new a hand-rolled wither returns.
func (n Node) IsWitherRebuild() bool {
	function := n.EnclosingFunctionLike()

	return function.Exists() && function.HandRolledWither().Node() == n.Node() && n.Exists()
}

// HandRolledWither is the new a method's only statement returns when it rebuilds its own class, carrying three or
// more of its own properties over and changing at least one, over a constructor that only promotes; no node
// otherwise.
func (n Node) HandRolledWither() Node {
	if !n.IsSoleReturnExpression() {
		return Node{}
	}
	built := Node{Match: n.In("stmts")[0].Child("expr")}
	if built.Kind() != "Expr_New" || !isName(built.Child("class")) || !n.namesOwnType(built.Child("class")) {
		return Node{}
	}
	carried, changed := 0, 0
	for _, argument := range built.Children() {
		if argument.Node().Field != "args" {
			continue
		}
		if argument.Kind() != "Arg" || slices.Contains(argument.Node().Flags, "spread") {
			return Node{}
		}
		if (Node{Match: argument.Child("value")}).IsOwnPropertyRead() {
			carried++
		} else {
			changed++
		}
	}
	if carried < witherCarriedFloor || changed < 1 || !n.ConstructorIsPromotionOnly() {
		return Node{}
	}

	return built
}

// IsSoleReturnExpression says whether the node declares a function whose only statement returns a value.
func (n Node) IsSoleReturnExpression() bool {
	body := n.In("stmts")

	return n.IsFunctionDeclaration() && len(body) == 1 && body[0].Kind() == "Stmt_Return" && body[0].Child("expr").Exists()
}

// namesOwnType says whether a written name is self, static or the class around the node.
func (n Node) namesOwnType(name engine.Match) bool {
	spelled := strings.ToLower(name.Name())
	if spelled == "self" || spelled == "static" {
		return true
	}
	enclosing := EnclosingClassName(n.Match)

	return enclosing != "" && strings.ToLower(strings.TrimLeft(name.Name(), `\`)) == strings.ToLower(enclosing)
}

// IsOwnPropertyRead says whether the node reads a named property of its own object: $this->total.
func (n Node) IsOwnPropertyRead() bool {
	object := n.Child("var")

	return n.Kind() == "Expr_PropertyFetch" && object.Kind() == "Expr_Variable" && object.Name() == "this" && n.Child("name").Kind() == "Identifier"
}

// ConstructorIsPromotionOnly says whether the class around the node has a constructor with parameters, each
// promoted and none variadic, and an empty body.
func (n Node) ConstructorIsPromotionOnly() bool {
	class := n.EnclosingClassLike()
	for _, method := range Methods(class.Match) {
		if !strings.EqualFold(method.Name(), "__construct") {
			continue
		}
		params := Params(method)
		if len(params) == 0 || len(Node{Match: method}.In("stmts")) > 0 {
			return false
		}

		return !slices.ContainsFunc(params, func(param engine.Match) bool {
			return len(param.Node().Modifiers) == 0 || slices.Contains(param.Node().Flags, "variadic")
		})
	}

	return false
}

// MutatesOwnFieldsAfterConstruction says whether the node is a class-like whose every field its constructor sets,
// yet a method other than the constructor, or a trait method it takes in, writes one of them again; a method that
// returns $this is a builder step and passes.
func (n Node) MutatesOwnFieldsAfterConstruction(inherited []engine.Match) bool {
	if !n.IsClassLike() {
		return false
	}
	constructed := n.constructedFieldNames()
	if len(constructed) == 0 {
		return false
	}
	for _, field := range Fields(n.Match) {
		if !constructed[field.Name] {
			return false
		}
	}
	for _, method := range append(Methods(n.Match), inherited...) {
		if method.Name() == "__construct" || returnsThis(method) {
			continue
		}
		for _, node := range descendantsOf(method) {
			if isFieldWrite(node) && constructed[selfPropertyOf(node.Child("var"))] {
				return true
			}
		}
	}

	return false
}

// constructedFieldNames is every field the constructor of the class around the node sets from a parameter: the
// promoted ones, and each $this->x assigned a parameter.
func (n Node) constructedFieldNames() map[string]bool {
	var constructor engine.Match
	for _, method := range Methods(n.EnclosingClassLike().Match) {
		if strings.EqualFold(method.Name(), "__construct") {
			constructor = method
		}
	}
	if !constructor.Exists() {
		return nil
	}
	names, parameters := map[string]bool{}, map[string]bool{}
	for _, param := range Params(constructor) {
		variable := param.Child("var")
		if variable.Kind() != "Expr_Variable" || variable.Name() == "" {
			continue
		}
		parameters[variable.Name()] = true
		if len(param.Node().Modifiers) > 0 {
			names[variable.Name()] = true
		}
	}
	for _, node := range descendantsOf(constructor) {
		value := node.Child("expr")
		if name := selfPropertyOf(node.Child("var")); node.Kind() == "Expr_Assign" && name != "" && value.Kind() == "Expr_Variable" && parameters[value.Name()] {
			names[name] = true
		}
	}

	return names
}

// selfPropertyOf is the property a $this->x read names; empty for any other node.
func selfPropertyOf(node engine.Match) string {
	if !(Node{Match: node}).IsOwnPropertyRead() {
		return ""
	}

	return node.Child("name").Name()
}

// returnsThis says whether a return of $this sits anywhere in the method.
func returnsThis(method engine.Match) bool {
	return slices.ContainsFunc(descendantsOf(method), func(node engine.Match) bool {
		returned := node.Child("expr")

		return node.Kind() == "Stmt_Return" && returned.Kind() == "Expr_Variable" && returned.Name() == "this"
	})
}

// isFieldWrite says whether the node assigns a property, a compound assignment included and ??= aside.
func isFieldWrite(node engine.Match) bool {
	assigns := node.Kind() == "Expr_Assign" || strings.HasPrefix(node.Kind(), "Expr_AssignOp_") && node.Kind() != "Expr_AssignOp_Coalesce"

	return assigns && node.Child("var").Kind() == "Expr_PropertyFetch"
}

// clumpScalars are the scalar types a data clump is made of.
var clumpScalars = []string{"string", "int", "float", "bool"}

// ValueParamSignature is the function's plainly scalar-typed parameters as "type $name", sorted, when there are
// three or more of them; none otherwise.
func (n Node) ValueParamSignature() []string {
	if !n.IsFunctionDeclaration() {
		return nil
	}
	var fields []string
	for _, param := range Params(n.Match) {
		declared, variable := param.Node().Declared, param.Child("var")
		if declared == nil || declared.Kind != "keyword" || declared.Nullable || variable.Kind() != "Expr_Variable" || variable.Name() == "" {
			continue
		}
		if scalar := strings.ToLower(declared.Name); slices.Contains(clumpScalars, scalar) {
			fields = append(fields, scalar+" $"+variable.Name())
		}
	}
	if len(fields) < 3 {
		return nil
	}
	slices.Sort(fields)

	return fields
}

// IsNamedConstructor says whether the function builds an instance of its own class: new self, new static, or new
// of its class's short name.
func (n Node) IsNamedConstructor() bool {
	if !n.IsFunctionDeclaration() {
		return false
	}
	short := ShortName(EnclosingClassName(n.Match))
	for _, statement := range n.In("stmts") {
		for _, node := range append([]engine.Match{statement}, descendantsOf(statement)...) {
			class := node.Child("class")
			if node.Kind() != "Expr_New" || !isName(class) {
				continue
			}
			name := strings.ToLower(class.Name())
			if name == "self" || name == "static" || short != "" && strings.EqualFold(ShortName(class.Name()), short) {
				return true
			}
		}
	}

	return false
}

// ArrayKeyIsString says whether the node reads an array offset by a string literal: $data['name'].
func (n Node) ArrayKeyIsString() bool {
	return n.Kind() == "Expr_ArrayDimFetch" && n.Child("dim").Kind() == "Scalar_String"
}

// ArrayBaseName is the variable an offset read reads from; empty when it reads from anything else.
func (n Node) ArrayBaseName() string {
	base := n.Child("var")
	if n.Kind() != "Expr_ArrayDimFetch" || base.Kind() != "Expr_Variable" {
		return ""
	}

	return base.Name()
}

// EnclosingParamIsArray says whether the function around the node takes the named parameter typed array, nullable
// or not.
func (n Node) EnclosingParamIsArray(name string) bool {
	if name == "" {
		return false
	}
	for _, param := range Params(n.EnclosingFunctionLike().Match) {
		if variable := param.Child("var"); variable.Kind() == "Expr_Variable" && variable.Name() == name {
			declared := param.Node().Declared

			return declared != nil && declared.Kind == "keyword" && declared.Name == "array"
		}
	}

	return false
}

// IsWithinNamedConstructor says whether the function around the node builds an instance of its own class.
func (n Node) IsWithinNamedConstructor() bool {
	function := n.EnclosingFunctionLike()

	return function.Exists() && function.IsNamedConstructor()
}

// IsWithinSerializationBoundary says whether the node sits in __unserialize or __set_state, whose raw array the
// language hands over, or in a method every caller of which does, three calls deep.
func (n Node) IsWithinSerializationBoundary() bool {
	return scopeIsSerializationBoundary(n.Codebase(), EnclosingClassName(n.Match), EnclosingFunctionName(n.Match), 0)
}

func scopeIsSerializationBoundary(codebase *engine.Codebase, class, method string, depth int) bool {
	if method == "__unserialize" || method == "__set_state" {
		return true
	}
	if class == "" || method == "" || depth >= 3 {
		return false
	}
	callers := IndexOf(codebase).CallersOf(class, method)

	return len(callers) > 0 && !slices.ContainsFunc(callers, func(caller engine.Match) bool {
		return !scopeIsSerializationBoundary(codebase, EnclosingClassName(caller), EnclosingFunctionName(caller), depth+1)
	})
}

// StringKeys is every string key an array literal writes, in order.
func (n Node) StringKeys() []string {
	var keys []string
	if n.Kind() != "Expr_Array" {
		return nil
	}
	for _, item := range n.In("items") {
		if key := item.Child("key"); item.Kind() == "ArrayItem" && key.Kind() == "Scalar_String" {
			text, _ := key.Text()
			keys = append(keys, text)
		}
	}

	return keys
}

// HasNestedArrayValue says whether an item of the array literal holds another array literal.
func (n Node) HasNestedArrayValue() bool {
	return n.Kind() == "Expr_Array" && slices.ContainsFunc(n.In("items"), func(item engine.Match) bool {
		return item.Kind() == "ArrayItem" && item.Child("value").Kind() == "Expr_Array"
	})
}

// SpreadsAnotherArray says whether an item of the array literal spreads another array into it.
func (n Node) SpreadsAnotherArray() bool {
	return n.Kind() == "Expr_Array" && slices.ContainsFunc(n.In("items"), func(item engine.Match) bool {
		return item.Kind() == "ArrayItem" && slices.Contains(item.Node().Flags, "spread")
	})
}

// LooksLikeJsonSchema says whether the array literal's keys are a JSON schema's: structural keys, or a type among
// JSON's with a describing key beside it.
func (n Node) LooksLikeJsonSchema() bool {
	if n.Kind() != "Expr_Array" {
		return false
	}
	keys := n.StringKeys()
	hasAny := func(wanted ...string) bool {
		return slices.ContainsFunc(wanted, func(key string) bool { return slices.Contains(keys, key) })
	}
	if hasAny("properties", "items", "enum", "required", "additionalProperties") {
		return true
	}
	if !slices.Contains(keys, "type") {
		return false
	}
	if kind, ok := n.literalForKey("type"); ok && !slices.Contains([]string{"object", "array", "string", "integer", "number", "boolean", "null"}, kind) {
		return false
	}

	return hasAny("description", "format", "title", "default", "nullable")
}

// literalForKey is the string an array literal writes under the key, when it writes one.
func (n Node) literalForKey(key string) (string, bool) {
	for _, item := range n.In("items") {
		written, value := item.Child("key"), item.Child("value")
		if text, ok := written.Text(); item.Kind() == "ArrayItem" && written.Kind() == "Scalar_String" && ok && text == key && value.Kind() == "Scalar_String" {
			return value.Text()
		}
	}

	return "", false
}

// IsHomogeneousLookupTable says whether every item of the array literal is a constant of one and the same class.
func (n Node) IsHomogeneousLookupTable() bool {
	items := n.In("items")
	if n.Kind() != "Expr_Array" || len(items) < 2 {
		return false
	}
	classes := map[string]bool{}
	for _, item := range items {
		value := item.Child("value")
		if item.Kind() != "ArrayItem" || value.Kind() != "Expr_ClassConstFetch" || !isName(value.Child("class")) {
			return false
		}
		classes[value.Child("class").Name()] = true
	}

	return len(classes) == 1
}

var (
	shapedReturn = regexp.MustCompile(`@return\s+\??array\s*\{`)
	namedField   = regexp.MustCompile(`\{[^}]*[A-Za-z_]\w*\??\s*:`)
)

// EnclosingFunctionReturnsShapedArray says whether the function around the node documents an array shape with
// named keys as its return.
func (n Node) EnclosingFunctionReturnsShapedArray() bool {
	doc, ok := n.EnclosingFunctionLike().DocComment()
	if !ok {
		return false
	}

	return slices.ContainsFunc(prose.Lines(doc.Text), func(line string) bool {
		return shapedReturn.MatchString(line) && namedField.MatchString(line)
	})
}

// ProjectsTypedObject says whether the keyed array literal only reads whole fields of one source that is already a
// type: $this, or a parameter declared as a value-type class.
func (n Node) ProjectsTypedObject() bool {
	source := n.arrayProjectionSource()
	switch source {
	case "":
		return false
	case "this":
		return true
	}
	for _, param := range Params(n.EnclosingFunctionLike().Match) {
		if variable := param.Child("var"); variable.Kind() == "Expr_Variable" && variable.Name() == source {
			declared := param.Node().Declared

			return Written(declared).Class() != "" && ProgramOf(n.Codebase()).IsValueType(declared)
		}
	}

	return false
}

// arrayProjectionSource is the one variable a keyed array literal's values all read whole fields of; empty when
// there is no such one variable.
func (n Node) arrayProjectionSource() string {
	items := n.In("items")
	if n.Kind() != "Expr_Array" || len(items) == 0 {
		return ""
	}
	var values []engine.Match
	for _, item := range items {
		if item.Kind() != "ArrayItem" || !item.Child("key").Exists() {
			return ""
		}
		values = append(values, item.Child("value"))
	}
	sources := map[string]bool{}
	for _, value := range values {
		for _, node := range expressionNodes(value) {
			if node.Kind() == "Expr_Variable" && node.Name() != "" {
				sources[node.Name()] = true
			}
		}
	}
	if len(sources) != 1 {
		return ""
	}
	var source string
	for name := range sources {
		source = name
	}
	for _, value := range values {
		if !readsOnlyOwnFieldsOf(value, source) {
			return ""
		}
	}

	return source
}

// expressionNodes is the node and every node under it, closures and arrow functions left unopened.
func expressionNodes(node engine.Match) []engine.Match {
	if !node.Exists() || node.Kind() == "Expr_Closure" || node.Kind() == "Expr_ArrowFunction" {
		return nil
	}
	nodes := []engine.Match{node}
	for _, child := range node.Children() {
		nodes = append(nodes, expressionNodes(child)...)
	}

	return nodes
}

// readsOnlyOwnFieldsOf says whether every use of the source in the value reads one of its fields whole.
func readsOnlyOwnFieldsOf(value engine.Match, source string) bool {
	for _, node := range expressionNodes(value) {
		if node.Kind() != "Expr_Variable" || node.Name() != source {
			continue
		}
		field := node.Parent()
		if field.Kind() != "Expr_PropertyFetch" && field.Kind() != "Expr_NullsafePropertyFetch" {
			return false
		}
		if !readsFieldWhole(field.Parent()) {
			return false
		}
	}

	return true
}

// readsFieldWhole says whether what is done with a field read keeps it whole: no offset into it, and no further
// read beyond an enum's value.
func readsFieldWhole(beyond engine.Match) bool {
	switch beyond.Kind() {
	case "Expr_ArrayDimFetch":
		return false
	case "Expr_PropertyFetch", "Expr_NullsafePropertyFetch":
		return beyond.Child("name").Kind() == "Identifier" && beyond.Child("name").Name() == "value"
	}

	return true
}
