package spatie

import (
	"maps"
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// assignedPropertyName is the field a `$this->field = …` assigns.
func assignedPropertyName(node engine.Match) string {
	target := node.Child("var")
	if node.Kind() != "Expr_Assign" || target.Kind() != "Expr_PropertyFetch" || !isThis(target.Child("var")) || target.Child("name").Kind() != "Identifier" {
		return ""
	}

	return target.Child("name").Name()
}

func isThis(node engine.Match) bool {
	return node.Kind() == "Expr_Variable" && node.Name() == "this"
}

func (n Node) assignedField() (php.Field, bool) {
	name := assignedPropertyName(n.Match)
	if name == "" {
		return php.Field{}, false
	}
	for _, field := range php.Fields(php.EnclosingClass(n.Match)) {
		if field.Name == name {
			return field, true
		}
	}

	return php.Field{}, false
}

// AssignedPropertyIsPublicSlot says whether `$this->field = …` fills a public property the constructor does not
// promote.
func (n Node) AssignedPropertyIsPublicSlot() bool {
	field, ok := n.assignedField()

	return ok && field.IsPublic && !field.Promoted
}

// AssignmentRhsIsDeferred says whether an assignment stores a deferred value: `Lazy::…`, or a `new …Prop`.
func (n Node) AssignmentRhsIsDeferred() bool {
	if n.Kind() != "Expr_Assign" {
		return false
	}
	value := n.Child("expr")
	class := value.Child("class")
	switch {
	case value.Kind() == "Expr_StaticCall" && isName(class):
		return php.ShortName(class.Name()) == "Lazy"
	case value.Kind() == "Expr_New" && isName(class):
		return strings.Contains(php.ShortName(class.Name()), "Prop")
	}

	return false
}

// AssignedSlotTypeIsDeferred says whether the field a `$this->field = …` fills is typed as a deferred value.
func (n Node) AssignedSlotTypeIsDeferred() bool {
	field, ok := n.assignedField()

	return ok && namesDeferral(field.Type)
}

func namesDeferral(written *contract.Type) bool {
	switch {
	case written == nil:
		return false
	case written.Kind == "union" || written.Kind == "intersection":
		return slices.ContainsFunc(written.Members, namesDeferral)
	case written.Kind == "named" || (written.Kind == "keyword" && len(php.Written(written).Names()) == 1):
		short := php.ShortName(written.Name)

		return short == "Lazy" || strings.HasSuffix(short, "Prop")
	}

	return false
}

// AssignmentReadsScopedState says whether an assignment's value reads request-scoped state: a `current()` accessor,
// itself or through the methods and constructors it calls, four calls deep.
func (n Node) AssignmentReadsScopedState() bool {
	return n.Kind() == "Expr_Assign" && n.scopedStateWithin(n.Child("expr"), enclosingFunction(n.Match), php.EnclosingClassName(n.Match), 4, map[string]bool{})
}

func (n Node) scopedStateWithin(node, function engine.Match, self string, depth int, visited map[string]bool) bool {
	if readsScopedAccessor(node) {
		return true
	}
	if depth <= 0 || !function.Exists() {
		return false
	}
	all := append([]engine.Match{node}, descendants(node)...)
	for _, call := range all {
		if call.Kind() == "Expr_MethodCall" && call.Child("name").Kind() == "Identifier" &&
			n.crossInto(n.types().TypeIn(call.Child("var"), function, self), call.Child("name").Name(), depth, visited) {
			return true
		}
	}
	for _, call := range all {
		if call.Kind() != "Expr_StaticCall" || call.Child("name").Kind() != "Identifier" || !isName(call.Child("class")) {
			continue
		}
		method := call.Child("name").Name()
		if method == "from" {
			method = "__construct"
		}
		if n.crossInto(resolveSelfClass(call.Child("class").Name(), self), method, depth, visited) {
			return true
		}
	}
	for _, built := range all {
		if built.Kind() == "Expr_New" && isName(built.Child("class")) && n.crossInto(resolveSelfClass(built.Child("class").Name(), self), "__construct", depth, visited) {
			return true
		}
	}

	return false
}

// crossInto follows a call into the method the class declares; each path keeps its own record of where it has been.
func (n Node) crossInto(class, method string, depth int, visited map[string]bool) bool {
	if class == "" {
		return false
	}
	key := class + "::" + method
	if visited[key] {
		return false
	}
	visited = maps.Clone(visited)
	visited[key] = true
	declaration, ok := n.program().Class(class)
	if !ok {
		return false
	}
	callee, ok := php.Method(declaration, method)

	return ok && n.scopedStateWithin(callee, callee, class, depth-1, visited)
}

func resolveSelfClass(class, self string) string {
	if class == "self" || class == "static" {
		return self
	}

	return strings.TrimLeft(class, `\`)
}

func readsScopedAccessor(node engine.Match) bool {
	for _, call := range append([]engine.Match{node}, descendants(node)...) {
		if (call.Kind() == "Expr_StaticCall" || call.Kind() == "Expr_MethodCall") && call.Child("name").Kind() == "Identifier" && call.Child("name").Name() == "current" {
			return true
		}
	}

	return false
}

// AssignedSlotIsEager says whether the property a `$this->field = …` fills, declared alone, is marked `#[Eager]`.
func (n Node) AssignedSlotIsEager() bool {
	name := assignedPropertyName(n.Match)
	class := php.EnclosingClass(n.Match)
	if name == "" || !class.Exists() {
		return false
	}
	for _, property := range class.Children() {
		if property.Kind() != "Stmt_Property" {
			continue
		}
		var items []engine.Match
		for _, item := range property.Children() {
			if item.Node().Field == "props" {
				items = append(items, item)
			}
		}
		if len(items) != 1 || items[0].Name() != name {
			continue
		}
		for _, attribute := range php.AttributeNames(property) {
			attribute = strings.TrimLeft(attribute, `\`)
			if attribute == "Eager" || strings.HasSuffix(attribute, `\Eager`) {
				return true
			}
		}
	}

	return false
}

// PropertyAssignedMoreThanOnce says whether the class's constructor assigns the field a `$this->field = …` fills
// more than once.
func (n Node) PropertyAssignedMoreThanOnce() bool {
	name := assignedPropertyName(n.Match)
	constructor, ok := php.Method(php.EnclosingClass(n.Match), "__construct")
	if name == "" || !ok {
		return false
	}
	count := 0
	for _, statement := range constructor.Children() {
		if statement.Node().Field != "stmts" {
			continue
		}
		for _, assign := range append([]engine.Match{statement}, descendants(statement)...) {
			if assignedPropertyName(assign) == name {
				count++
			}
		}
	}

	return count > 1
}

// IsOptionalAbsentMarker says whether the node builds Spatie's Optional: `new Optional` or `Optional::create()`.
func (n Node) IsOptionalAbsentMarker() bool {
	if n.Kind() == "Expr_New" {
		return strings.TrimLeft(newClassName(n.Match), `\`) == Optional
	}
	class, name := n.Child("class"), n.Child("name")

	return n.Kind() == "Expr_StaticCall" && isName(class) && strings.TrimLeft(class.Name(), `\`) == Optional && name.Kind() == "Identifier" && name.Name() == "create"
}

// IsOptionalNullFallback says whether an Optional is what a null falls back to: `$x ?? new Optional`, or a ternary
// on a null check.
func (n Node) IsOptionalNullFallback() bool {
	if !n.IsOptionalAbsentMarker() {
		return false
	}
	parent := n.Parent()
	switch parent.Kind() {
	case "Expr_Ternary":
		if n.Node().Field != "if" && n.Node().Field != "else" {
			return false
		}

		return !parent.Child("if").Exists() || isNullComparison(parent.Child("cond"))
	case "Expr_BinaryOp_Coalesce":
		return n.Node().Field == "right"
	}

	return false
}

func isNullComparison(node engine.Match) bool {
	switch node.Kind() {
	case "Expr_BinaryOp_Identical", "Expr_BinaryOp_NotIdentical":
		return php.IsNullConstant(node.Child("left")) || php.IsNullConstant(node.Child("right"))
	case "Expr_FuncCall":
		return isName(node.Child("name")) && strings.ToLower(node.Child("name").Name()) == "is_null"
	}

	return false
}

// IsSharedOptionalFactory says whether an Optional is one branch of a factory's own absent-or-present choice: a
// parameter coalesced to it, or a ternary whose other branch is `self::from`.
func (n Node) IsSharedOptionalFactory() bool {
	if !n.IsOptionalAbsentMarker() {
		return false
	}
	parent := n.Parent()
	switch parent.Kind() {
	case "Expr_BinaryOp_Coalesce":
		return n.Node().Field == "right" && n.coalescesEnclosingParameter(parent.Child("left"))
	case "Expr_Ternary":
		present := parent.Child("if")
		if n.Node().Field == "if" {
			present = parent.Child("else")
		}
		class, name := present.Child("class"), present.Child("name")

		return present.Kind() == "Expr_StaticCall" && isName(class) && (class.Name() == "self" || class.Name() == "static") && name.Kind() == "Identifier" && name.Name() == "from"
	}

	return false
}

func (n Node) coalescesEnclosingParameter(left engine.Match) bool {
	function := enclosingFunction(n.Match)
	if left.Kind() != "Expr_Variable" || left.Name() == "" || !function.Exists() {
		return false
	}
	for _, param := range php.Params(function) {
		if variable := param.Child("var"); variable.Kind() == "Expr_Variable" && variable.Name() == left.Name() {
			return true
		}
	}

	return false
}

// IsReplaceableNewOptional says whether a `new Optional` could be `Optional::create()`: it is no default value and no
// attribute argument, where only a constant expression may stand.
func (n Node) IsReplaceableNewOptional() bool {
	if n.Kind() != "Expr_New" || strings.TrimLeft(newClassName(n.Match), `\`) != Optional {
		return false
	}
	if parent := n.Parent().Kind(); parent == "Param" || parent == "PropertyItem" {
		return false
	}

	return !within(n.Match, func(at engine.Match) bool { return at.Kind() == "Attribute" })
}

// TransformerLacksTsType says whether a field's `#[WithTransformer]` names a transformer whose TypeScript type is not
// known, and the field gives none of its own.
func (n Node) TransformerLacksTsType() bool {
	if n.Kind() != "Attribute" || slices.Contains(knownTsTransformers, firstArgumentClassShortName(n.Match)) {
		return false
	}
	carrier := n.Parent()
	for carrier.Exists() && carrier.Kind() != "Stmt_Property" && carrier.Kind() != "Param" {
		carrier = carrier.Parent()
	}
	if !carrier.Exists() {
		return false
	}
	field, ok := php.AsField(carrier)

	return ok && !field.HasAttribute("TypeScriptType", "LiteralTypeScriptType")
}

func firstArgumentClassShortName(attribute engine.Match) string {
	value := firstArgument(attribute).Child("value")
	if class := value.Child("class"); value.Kind() == "Expr_ClassConstFetch" && isName(class) {
		return php.ShortName(strings.TrimLeft(class.Name(), `\`))
	}

	return ""
}

// FlattensValueObjectToArray says whether a public array slot, or a function's sole returned array, reads its
// entries off one value object that is neither Data nor an enum, flattening what could be passed whole.
func (n Node) FlattensValueObjectToArray() bool {
	array, ok := n.publicSlotArrayOutput()
	if !ok {
		return false
	}
	receiver, shared := php.SharedFetchReceiver(array)
	function := enclosingFunction(n.Match)
	if !shared || !function.Exists() {
		return false
	}
	class := n.types().TypeIn(receiver, function, php.EnclosingClassName(n.Match))

	return class != "" && !n.program().Extends(class, Data) && !n.program().IsEnum(class)
}

func (n Node) publicSlotArrayOutput() (engine.Match, bool) {
	if n.Kind() == "Expr_Assign" {
		value := n.Child("expr")

		return value, value.Kind() == "Expr_Array" && n.AssignedPropertyIsPublicSlot()
	}

	return soleArrayLiteralOutput(n.Match)
}

// soleArrayLiteralOutput is the array literal a hook is, or a method or function only returns.
func soleArrayLiteralOutput(node engine.Match) (engine.Match, bool) {
	switch node.Kind() {
	case "PropertyHook":
		if body := node.Child("body"); body.Kind() == "Expr_Array" {
			return body, true
		}

		return soleReturnedArray(node, "body")
	case "Stmt_ClassMethod", "Stmt_Function":
		return soleReturnedArray(node, "stmts")
	}

	return engine.Match{}, false
}

func soleReturnedArray(node engine.Match, field string) (engine.Match, bool) {
	var statements []engine.Match
	for _, statement := range node.Children() {
		if statement.Node().Field == field && statement.Node().Role == "statement" {
			statements = append(statements, statement)
		}
	}
	if len(statements) != 1 || statements[0].Kind() != "Stmt_Return" {
		return engine.Match{}, false
	}
	value := statements[0].Child("expr")

	return value, value.Kind() == "Expr_Array"
}

// TransformerOutputIn is the folder the TypeScript transformer writes into, as the codebase configures it: an
// `outputDirectory(...)` call, or an `output_file` config key, read from literals, concatenation, variables, `__DIR__`,
// `dirname` and Laravel's path helpers. Empty when the codebase says nothing a reader can resolve.
func TransformerOutputIn(codebase *engine.Codebase) string {
	for _, file := range codebase.Of(contract.PHP).Files() {
		path := file.Path
		if real, err := filepath.EvalSymlinks(path); err == nil {
			path = real
		}
		nodes := allOf(file)
		assignments := map[string]engine.Match{}
		for _, assign := range nodes {
			if variable := assign.Child("var"); assign.Kind() == "Expr_Assign" && variable.Kind() == "Expr_Variable" && variable.Name() != "" {
				assignments[variable.Name()] = assign.Child("expr")
			}
		}
		for _, call := range nodes {
			if call.Kind() != "Expr_MethodCall" || call.Child("name").Kind() != "Identifier" || call.Child("name").Name() != "outputDirectory" || !firstArgument(call).Exists() {
				continue
			}
			if directory, ok := evaluate(firstArgument(call).Child("value"), path, assignments); ok {
				return normalise(directory)
			}
		}
		for _, item := range nodes {
			if key := item.Child("key"); item.Kind() == "ArrayItem" && key.Kind() == "Scalar_String" {
				if written, _ := key.Node().Value.Text(); written == "output_file" {
					if output, ok := evaluate(item.Child("value"), path, assignments); ok {
						return normalise(output)
					}

					break
				}
			}
		}
	}

	return ""
}

func evaluate(expr engine.Match, file string, assignments map[string]engine.Match) (string, bool) {
	switch expr.Kind() {
	case "Scalar_String":
		return expr.Node().Value.Text()
	case "Expr_BinaryOp_Concat":
		left, leftOK := evaluate(expr.Child("left"), file, assignments)
		right, rightOK := evaluate(expr.Child("right"), file, assignments)

		return left + right, leftOK && rightOK
	case "Expr_Variable":
		bound, ok := assignments[expr.Name()]
		if expr.Name() == "" || !ok {
			return "", false
		}

		return evaluate(bound, file, assignments)
	case "Scalar_MagicConst_Dir":
		return filepath.Dir(file), true
	case "Scalar_MagicConst_File":
		return file, true
	case "Expr_FuncCall":
		if isName(expr.Child("name")) {
			return evaluateCall(expr, file, assignments)
		}
	}

	return "", false
}

// pathHelpers are Laravel's path helpers and the folder under the project root each names.
var pathHelpers = map[string]string{"base_path": "", "resource_path": "resources", "app_path": "app", "config_path": "config", "storage_path": "storage"}

func evaluateCall(call engine.Match, file string, assignments map[string]engine.Match) (string, bool) {
	name := call.Child("name").Name()
	var arguments []engine.Match
	for _, argument := range call.Children() {
		if argument.Node().Field == "args" {
			arguments = append(arguments, argument)
		}
	}
	if name == "dirname" && len(arguments) > 0 {
		base, ok := evaluate(arguments[0].Child("value"), file, assignments)
		levels := 1
		if len(arguments) > 1 && arguments[1].Child("value").Kind() == "Scalar_Int" {
			written, _ := arguments[1].Child("value").Node().Value.Text()
			if parsed, err := strconv.Atoi(written); err == nil {
				levels = parsed
			}
		}
		for range max(1, levels) {
			base = filepath.Dir(base)
		}

		return base, ok
	}
	folder, ok := pathHelpers[name]
	if !ok {
		return "", false
	}
	root, found := projectRoot(file)
	suffix, known := "", true
	if len(arguments) > 0 {
		suffix, known = evaluate(arguments[0].Child("value"), file, assignments)
	}
	if !found || !known {
		return "", false
	}

	return strings.TrimRight(root+"/"+folder+"/"+strings.TrimLeft(suffix, "/"), "/"), true
}

// projectRoot is the nearest folder above the file holding a composer.json.
func projectRoot(file string) (string, bool) {
	for folder := filepath.Dir(file); folder != "/" && folder != "" && folder != "."; folder = filepath.Dir(folder) {
		if info, err := os.Stat(filepath.Join(folder, "composer.json")); err == nil && !info.IsDir() {
			return folder, true
		}
	}

	return "", false
}

// normalise is a path with `.` and `..` resolved and empty parts dropped, always absolute.
func normalise(path string) string {
	var segments []string
	for _, segment := range strings.Split(path, "/") {
		switch segment {
		case "", ".":
		case "..":
			if len(segments) > 0 {
				segments = segments[:len(segments)-1]
			}
		default:
			segments = append(segments, segment)
		}
	}

	return "/" + strings.Join(segments, "/")
}

func allOf(file *engine.File) []engine.Match {
	var nodes []engine.Match
	for _, node := range file.Nodes() {
		nodes = append(nodes, file.Match(node.ID))
	}

	return nodes
}
