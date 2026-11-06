package php

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/prose"
)

const witherCarriedFloor = 3

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

func (n Node) IsPositionalTuple() bool {
	items := n.ChildrenIn("items")
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

func (n Node) IsReturnExpression() bool {
	parent := n.Parent()

	return n.Exists() && (parent.Kind() == "Stmt_Return" || parent.Kind() == "Expr_ArrowFunction" && n.Node().Field == "expr")
}

var sequenceReturns = []*regexp.Regexp{
	regexp.MustCompile(`@return\s+\??(non-empty-)?list\s*<`),
	regexp.MustCompile(`@return\s+\??array\s*<\s*int\s*,`),
	regexp.MustCompile(`@return\s+\??[\\\w|]+\[\]`),
}

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

func (n Node) DecodesItsOwnEncoding() bool {
	argument := n.Argument(0)
	for strings.HasPrefix(argument.Kind(), "Expr_Cast_") {
		argument = Node{Match: argument.Child("expr")}
	}

	return argument.Exists() && argument.CallName() == "json_encode"
}

func (n Node) IsWitherRebuild() bool {
	function := n.EnclosingFunctionLike()

	return function.Exists() && function.HandRolledWither().Node() == n.Node() && n.Exists()
}

func (n Node) HandRolledWither() Node {
	if !n.IsSoleReturnExpression() {
		return Node{}
	}
	built := Node{Match: n.ChildrenIn("stmts")[0].Child("expr")}
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

func (n Node) IsSoleReturnExpression() bool {
	body := n.ChildrenIn("stmts")

	return n.IsFunctionDeclaration() && len(body) == 1 && body[0].Kind() == "Stmt_Return" && body[0].Child("expr").Exists()
}

func (n Node) namesOwnType(name engine.Match) bool {
	spelled := strings.ToLower(name.Name())
	if spelled == "self" || spelled == "static" {
		return true
	}
	enclosing := EnclosingClassName(n.Match)

	return enclosing != "" && strings.ToLower(strings.TrimLeft(name.Name(), `\`)) == strings.ToLower(enclosing)
}

func (n Node) IsOwnPropertyRead() bool {
	object := n.Child("var")

	return n.Kind() == "Expr_PropertyFetch" && object.Kind() == "Expr_Variable" && object.Name() == "this" && n.Child("name").Kind() == "Identifier"
}

func (n Node) ConstructorIsPromotionOnly() bool {
	class := n.EnclosingClassLike()
	for _, method := range Methods(class.Match) {
		if !strings.EqualFold(method.Name(), "__construct") {
			continue
		}
		params := Params(method)
		if len(params) == 0 || len(Node{Match: method}.ChildrenIn("stmts")) > 0 {
			return false
		}

		return !slices.ContainsFunc(params, func(param engine.Match) bool {
			return len(param.Node().Modifiers) == 0 || slices.Contains(param.Node().Flags, "variadic")
		})
	}

	return false
}

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

func selfPropertyOf(node engine.Match) string {
	if !(Node{Match: node}).IsOwnPropertyRead() {
		return ""
	}

	return node.Child("name").Name()
}

func returnsThis(method engine.Match) bool {
	return slices.ContainsFunc(descendantsOf(method), func(node engine.Match) bool {
		returned := node.Child("expr")

		return node.Kind() == "Stmt_Return" && returned.Kind() == "Expr_Variable" && returned.Name() == "this"
	})
}

func isFieldWrite(node engine.Match) bool {
	assigns := node.Kind() == "Expr_Assign" || strings.HasPrefix(node.Kind(), "Expr_AssignOp_") && node.Kind() != "Expr_AssignOp_Coalesce"

	return assigns && node.Child("var").Kind() == "Expr_PropertyFetch"
}

var clumpScalars = []string{"string", "int", "float", "bool"}

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

func (n Node) IsNamedConstructor() bool {
	if !n.IsFunctionDeclaration() {
		return false
	}
	short := ShortName(EnclosingClassName(n.Match))
	for _, statement := range n.ChildrenIn("stmts") {
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

func (n Node) ArrayKeyIsString() bool {
	return n.Kind() == "Expr_ArrayDimFetch" && n.Child("dim").Kind() == "Scalar_String"
}

func (n Node) ArrayBaseName() string {
	base := n.Child("var")
	if n.Kind() != "Expr_ArrayDimFetch" || base.Kind() != "Expr_Variable" {
		return ""
	}

	return base.Name()
}

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

func (n Node) IsWithinNamedConstructor() bool {
	function := n.EnclosingFunctionLike()

	return function.Exists() && function.IsNamedConstructor()
}

func (n Node) IsWithinInheritedMethod() bool {
	return Node{Match: n.EnclosingFunction()}.NameIsInherited()
}

func (n Node) IsWithinADataFile() bool {
	answers := false

	for _, statement := range n.Root().ChildrenIn("stmts") {
		if statement.Is(engine.TypeDeclaration) || statement.Is(engine.Function) {
			return false
		}
		if statement.Is(engine.Return) && statement.Child("expr").Kind() == "Expr_Array" {
			answers = true
		}
	}

	return answers
}

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

func (n Node) StringKeys() []string {
	var keys []string
	if n.Kind() != "Expr_Array" {
		return nil
	}
	for _, item := range n.ChildrenIn("items") {
		if key := item.Child("key"); item.Kind() == "ArrayItem" && key.Kind() == "Scalar_String" {
			text, _ := key.Text()
			keys = append(keys, text)
		}
	}

	return keys
}

func (n Node) HasNestedArrayValue() bool {
	return n.Kind() == "Expr_Array" && slices.ContainsFunc(n.ChildrenIn("items"), func(item engine.Match) bool {
		return item.Kind() == "ArrayItem" && item.Child("value").Kind() == "Expr_Array"
	})
}

func (n Node) SpreadsAnotherArray() bool {
	return n.Kind() == "Expr_Array" && slices.ContainsFunc(n.ChildrenIn("items"), func(item engine.Match) bool {
		return item.Kind() == "ArrayItem" && slices.Contains(item.Node().Flags, "spread")
	})
}

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

func (n Node) literalForKey(key string) (string, bool) {
	for _, item := range n.ChildrenIn("items") {
		written, value := item.Child("key"), item.Child("value")
		if text, ok := written.Text(); item.Kind() == "ArrayItem" && written.Kind() == "Scalar_String" && ok && text == key && value.Kind() == "Scalar_String" {
			return value.Text()
		}
	}

	return "", false
}

func (n Node) IsHomogeneousLookupTable() bool {
	items := n.ChildrenIn("items")
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

func (n Node) EnclosingFunctionReturnsShapedArray() bool {
	doc, ok := n.EnclosingFunctionLike().DocComment()
	if !ok {
		return false
	}

	return slices.ContainsFunc(prose.Lines(doc.Text), func(line string) bool {
		return shapedReturn.MatchString(line) && namedField.MatchString(line)
	})
}

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

func (n Node) arrayProjectionSource() string {
	items := n.ChildrenIn("items")
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

func readsFieldWhole(beyond engine.Match) bool {
	switch beyond.Kind() {
	case "Expr_ArrayDimFetch":
		return false
	case "Expr_PropertyFetch", "Expr_NullsafePropertyFetch":
		return beyond.Child("name").Kind() == "Identifier" && beyond.Child("name").Name() == "value"
	}

	return true
}
