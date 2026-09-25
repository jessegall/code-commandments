package php

import (
	"fmt"
	"path/filepath"
	"slices"
	"strconv"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// maxFlowSteps bounds one field's walk, so a flow through a large program ends.
const maxFlowSteps = 5000

// typePredicates are the functions that ask a value's type, and so guard it.
var typePredicates = []string{
	"is_null", "is_string", "is_array", "is_int", "is_integer", "is_float", "is_bool",
	"is_object", "is_scalar", "is_iterable", "is_callable", "is_numeric", "is_countable",
}

// FlowVerdict counts where a field's value arrives: the uses that assume it is set, and the ones that guard it.
// An untraceable field is none of either.
type FlowVerdict struct {
	Assume int
	Guard  int
}

// ValueFlow follows each field's value from every read of it, through assignments, arguments, returns, other
// fields and array slots, to where it is finally used: dereferenced or passed where null is refused, or guarded.
type ValueFlow struct {
	types      *Types
	index      *Index
	program    *Program
	reads      map[string]map[string][]engine.Match
	unresolved map[string]bool
	files      map[string]*engine.File

	mutex    sync.Mutex
	verdicts map[string]FlowVerdict
}

var flows = Memoised(gatherFlow)

// ValueFlowOf is the codebase's value flow, its field reads gathered on first need.
func ValueFlowOf(codebase *engine.Codebase) *ValueFlow {
	return flows.Of(codebase)
}

func gatherFlow(codebase *engine.Codebase) *ValueFlow {
	flow := &ValueFlow{
		types:      TypesOf(codebase),
		index:      IndexOf(codebase),
		program:    ProgramOf(codebase),
		reads:      map[string]map[string][]engine.Match{},
		unresolved: map[string]bool{},
		files:      map[string]*engine.File{},
		verdicts:   map[string]FlowVerdict{},
	}
	for _, file := range codebase.Of(contract.PHP).Files() {
		for _, node := range file.Nodes() {
			switch {
			case node.Kind == "Stmt_Class" && node.Symbol != "":
				flow.files[node.Symbol] = file
			case node.Kind == "Expr_PropertyFetch":
				flow.gather(file.Match(node.ID))
			}
		}
	}

	return flow
}

// gather files a read under the class that declares the field; a read of a receiver it cannot type leaves every
// field of that name untraceable.
func (f *ValueFlow) gather(fetch engine.Match) {
	name := fetch.Child("name")
	if name.Kind() != "Identifier" {
		return
	}
	function := enclosingFunction(fetch)
	class := f.types.TypeIn(fetch.Child("var"), function, flowClass(function))
	if class == "" {
		f.unresolved[name.Name()] = true

		return
	}
	owner := f.types.DeclaringClassOf(class, name.Name())
	if owner == "" {
		owner = class
	}
	if f.reads[owner] == nil {
		f.reads[owner] = map[string][]engine.Match{}
	}
	f.reads[owner][name.Name()] = append(f.reads[owner][name.Name()], fetch)
}

// Verdict is the field's verdict; untraceable when any read of its name escapes the engine.
func (f *ValueFlow) Verdict(fqcn, field string) FlowVerdict {
	if fqcn == "" {
		return FlowVerdict{}
	}
	fqcn = strings.TrimLeft(fqcn, `\`)
	key := fqcn + "::" + field
	f.mutex.Lock()
	verdict, ok := f.verdicts[key]
	f.mutex.Unlock()
	if ok {
		return verdict
	}
	if !f.unresolved[field] {
		walk := f.walk(fqcn, field)
		verdict = FlowVerdict{Assume: len(walk.assume), Guard: len(walk.guard)}
	}
	f.mutex.Lock()
	f.verdicts[key] = verdict
	f.mutex.Unlock()

	return verdict
}

// Explain is where the field's value is assumed set, and where it is guarded.
func (f *ValueFlow) Explain(fqcn, field string) (assume, guard []engine.Match) {
	walk := f.walk(strings.TrimLeft(fqcn, `\`), field)

	return walk.assume, walk.guard
}

// ChainPath is the longest road a read of the field takes to a use that assumes it, by the files it crosses: each
// step `<edge>@<file name>`.
func (f *ValueFlow) ChainPath(fqcn, field string) []string {
	if fqcn == "" {
		return nil
	}
	var deepest []string
	for _, chain := range f.walk(strings.TrimLeft(fqcn, `\`), field).chains {
		if filesCrossed(chain) > filesCrossed(deepest) {
			deepest = chain
		}
	}

	return deepest
}

func filesCrossed(chain []string) int {
	var files []string
	for _, step := range chain {
		_, file, _ := strings.Cut(step, "@")
		if !slices.Contains(files, file) {
			files = append(files, file)
		}
	}

	return len(files)
}

// reached is one place a value moves on to: the node, the edge it took, and the array key it rides under, if any.
type reached struct {
	node engine.Match
	edge string
	key  *string
}

type walk struct {
	assume, guard []engine.Match
	chains        [][]string
}

type visit struct {
	node *contract.Node
	key  string
}

type pending struct {
	reached
	path []string
}

func (f *ValueFlow) walk(fqcn, field string) walk {
	var result walk
	var queue []pending
	for _, read := range f.reads[fqcn][field] {
		queue = append(queue, pending{reached: reached{node: read, edge: "read"}})
	}
	seen := map[visit]bool{}
	slots := map[string]bool{}
	for steps := 0; len(queue) > 0 && steps < maxFlowSteps; steps++ {
		next := queue[len(queue)-1]
		queue = queue[:len(queue)-1]
		occurrence := next.node
		if !occurrence.Exists() {
			continue
		}
		at := visit{node: occurrence.Node()}
		if next.key != nil {
			at.key = *next.key
		}
		if seen[at] {
			continue
		}
		seen[at] = true
		path := append(slices.Clone(next.path), next.edge+"@"+filepath.Base(occurrence.File()))
		switch {
		case next.key == nil && (isNullGuardedUse(occurrence) || isSelfReadGuardedByStateClause(occurrence)):
			result.guard = append(result.guard, occurrence)
		case next.key == nil && (isDereferenced(occurrence) || f.flowsToNonNullableParam(occurrence)):
			result.assume = append(result.assume, occurrence)
			result.chains = append(result.chains, path)
		default:
			for _, moved := range f.follow(occurrence, next.key, slots) {
				queue = append(queue, pending{reached: moved, path: path})
			}
		}
	}

	return result
}

func (f *ValueFlow) follow(occurrence engine.Match, key *string, slots map[string]bool) []reached {
	moved := slices.Concat(
		keyed(f.viaAssignment(occurrence), "assign", key),
		keyed(f.viaArgument(occurrence, slots), "arg", key),
		keyed(f.viaReturn(occurrence, slots), "return", key),
	)
	if key == nil {
		return slices.Concat(moved, keyed(f.viaFieldWrite(occurrence, slots), "field", nil), viaArrayInsertion(occurrence))
	}

	return slices.Concat(moved, f.viaArrayElement(occurrence, *key), keyed(f.viaFromArray(occurrence, *key, slots), "from", nil))
}

func keyed(downstream []engine.Match, edge string, key *string) []reached {
	var moved []reached
	for _, node := range downstream {
		moved = append(moved, reached{node: node, edge: edge, key: key})
	}

	return moved
}

// viaArrayInsertion carries a value into the array literal it is an element of, under its literal key.
func viaArrayInsertion(occurrence engine.Match) []reached {
	item := occurrence.Parent()
	if !isChild(occurrence, "ArrayItem", "value") || item.Parent().Kind() != "Expr_Array" {
		return nil
	}
	key := literalKey(item)

	return []reached{{node: item.Parent(), edge: "array", key: &key}}
}

// viaArrayElement takes the value back out of the array that carries it: an element fetch by its key, a foreach's
// value, or a spread into another array.
func (f *ValueFlow) viaArrayElement(occurrence engine.Match, key string) []reached {
	parent := occurrence.Parent()
	switch {
	case isChild(occurrence, "Expr_ArrayDimFetch", "var"):
		if keyMatches(parent.Child("dim"), key) {
			return []reached{{node: parent, edge: "element"}}
		}

		return nil
	case isChild(occurrence, "Stmt_Foreach", "expr") && parent.Child("valueVar").Kind() == "Expr_Variable" && parent.Child("valueVar").Name() != "":
		return keyed(readsOf(parent.Child("valueVar").Name(), enclosingFunction(occurrence)), "element", nil)
	case parent.Kind() == "ArrayItem" && slices.Contains(parent.Node().Flags, "spread") && parent.Parent().Kind() == "Expr_Array":
		return []reached{{node: parent.Parent(), edge: "spread", key: &key}}
	}

	return nil
}

// viaFromArray follows an array handed to a `::from…` factory into the field its key names.
func (f *ValueFlow) viaFromArray(occurrence engine.Match, key string, slots map[string]bool) []engine.Match {
	call := occurrence.Parent().Parent()
	if occurrence.Parent().Kind() != "Arg" || key == "*" || call.Kind() != "Expr_StaticCall" || !isName(call.Child("class")) ||
		call.Child("name").Kind() != "Identifier" || !strings.HasPrefix(call.Child("name").Name(), "from") {
		return nil
	}

	return f.fieldSlotReads(strings.TrimLeft(call.Child("class").Name(), `\`), key, slots)
}

func literalKey(item engine.Match) string {
	key := item.Child("key")
	switch key.Kind() {
	case "Scalar_String", "Scalar_Int":
		if value, ok := key.Node().Value.Text(); ok {
			return value
		}
	}

	return "*"
}

func keyMatches(dim engine.Match, key string) bool {
	if key == "*" {
		return true
	}
	if dim.Kind() != "Scalar_String" && dim.Kind() != "Scalar_Int" {
		return false
	}
	value, ok := dim.Node().Value.Text()

	return ok && value == key
}

// viaAssignment follows a value assigned to a variable into every read of that variable.
func (f *ValueFlow) viaAssignment(occurrence engine.Match) []engine.Match {
	target := occurrence.Parent().Child("var")
	if !isChild(occurrence, "Expr_Assign", "expr") || target.Kind() != "Expr_Variable" {
		return nil
	}

	return readsOf(target.Name(), enclosingFunction(occurrence))
}

// viaArgument follows a value passed as an argument into the reads of the parameter it lands in.
func (f *ValueFlow) viaArgument(occurrence engine.Match, slots map[string]bool) []engine.Match {
	if occurrence.Parent().Kind() != "Arg" {
		return nil
	}
	target, ok := f.targetParam(occurrence.Parent())
	if !ok {
		return nil
	}

	return f.readsReachedThrough(target, slots)
}

func (f *ValueFlow) readsReachedThrough(target paramTarget, slots map[string]bool) []engine.Match {
	name := target.name()
	if name == "" || !mark(slots, fmt.Sprintf("P:%s::%s#%s", target.class, target.method, name)) {
		return nil
	}
	method, _ := f.methodNode(target.class, target.method)
	downstream := []engine.Match(nil)
	if _, known := f.files[target.class]; known {
		downstream = readsOf(name, method)
	}
	if !slices.Contains(target.param.Node().Flags, "promoted") {
		return downstream
	}

	return slices.Concat(downstream, f.fieldSlotReads(target.class, name, slots))
}

// viaReturn follows a method's returned value to every call of it.
func (f *ValueFlow) viaReturn(occurrence engine.Match, slots map[string]bool) []engine.Match {
	function := enclosingFunction(occurrence)
	if !isChild(occurrence, "Stmt_Return", "expr") || function.Kind() != "Stmt_ClassMethod" {
		return nil
	}
	class := flowClass(function)
	if class == "" || !mark(slots, "R:"+class+"::"+function.Name()) {
		return nil
	}

	return f.index.CallersOf(class, function.Name())
}

// viaFieldWrite follows a value written to a field into every read of that field.
func (f *ValueFlow) viaFieldWrite(occurrence engine.Match, slots map[string]bool) []engine.Match {
	target := occurrence.Parent().Child("var")
	if !isChild(occurrence, "Expr_Assign", "expr") || target.Kind() != "Expr_PropertyFetch" || target.Child("name").Kind() != "Identifier" {
		return nil
	}
	function := enclosingFunction(occurrence)
	owner := f.types.TypeIn(target.Child("var"), function, flowClass(function))
	if owner == "" {
		return nil
	}

	return f.fieldSlotReads(owner, target.Child("name").Name(), slots)
}

func (f *ValueFlow) flowsToNonNullableParam(occurrence engine.Match) bool {
	if occurrence.Parent().Kind() != "Arg" {
		return false
	}
	target, ok := f.targetParam(occurrence.Parent())

	return ok && !acceptsNull(target.param)
}

type paramTarget struct {
	class, method string
	param         engine.Match
}

func (p paramTarget) name() string {
	if variable := p.param.Child("var"); variable.Kind() == "Expr_Variable" {
		return variable.Name()
	}

	return ""
}

// targetParam is the parameter an argument lands in: by its name, or its position when unpacked it has none.
func (f *ValueFlow) targetParam(arg engine.Match) (paramTarget, bool) {
	call := arg.Parent()
	class, method := f.callee(call)
	if method == "" {
		return paramTarget{}, false
	}
	declaration, _ := f.methodNode(class, method)
	declared := Params(declaration)
	if len(declared) == 0 {
		return paramTarget{}, false
	}
	if name := arg.Child("name"); name.Kind() == "Identifier" {
		for _, param := range declared {
			if variable := param.Child("var"); variable.Kind() == "Expr_Variable" && variable.Name() == name.Name() {
				return paramTarget{class: class, method: method, param: param}, true
			}
		}

		return paramTarget{}, false
	}
	position, ok := positionOf(call, arg)
	if !ok || position >= len(declared) {
		return paramTarget{}, false
	}

	return paramTarget{class: class, method: method, param: declared[position]}, true
}

// callee is the class and method a call reaches: a method send on its receiver's type, a static call on the class
// it names, a construction's constructor.
func (f *ValueFlow) callee(call engine.Match) (class, method string) {
	switch call.Kind() {
	case "Expr_MethodCall":
		if name := call.Child("name"); name.Kind() == "Identifier" {
			function := enclosingFunction(call)
			if receiver := f.types.TypeIn(call.Child("var"), function, flowClass(function)); receiver != "" {
				return receiver, name.Name()
			}
		}
	case "Expr_StaticCall":
		if class, name := call.Child("class"), call.Child("name"); isName(class) && name.Kind() == "Identifier" {
			return strings.TrimLeft(class.Name(), `\`), name.Name()
		}
	case "Expr_New":
		if class := call.Child("class"); isName(class) {
			return strings.TrimLeft(class.Name(), `\`), "__construct"
		}
	}

	return "", ""
}

func positionOf(call, arg engine.Match) (int, bool) {
	if call.Kind() != "Expr_MethodCall" && call.Kind() != "Expr_StaticCall" && call.Kind() != "Expr_New" {
		return 0, false
	}
	position := 0
	for _, candidate := range call.Children() {
		if candidate.Node().Field != "args" {
			continue
		}
		if candidate.Node() == arg.Node() {
			return position, !slices.Contains(arg.Node().Flags, "spread")
		}
		position++
	}

	return 0, false
}

func (f *ValueFlow) fieldSlotReads(class, field string, slots map[string]bool) []engine.Match {
	if !mark(slots, "F:"+class+"::"+field) {
		return nil
	}

	return f.reads[class][field]
}

// methodNode is the method the class declares, the class being a class, not an interface, trait or enum.
func (f *ValueFlow) methodNode(class, method string) (engine.Match, bool) {
	declaration, ok := f.program.Class(class)
	if !ok {
		return engine.Match{}, false
	}

	return Method(declaration, method)
}

func mark(slots map[string]bool, slot string) bool {
	if slots[slot] {
		return false
	}
	slots[slot] = true

	return true
}

// readsOf is every use of the variable in the function, nested functions' included, that does not assign it.
func readsOf(name string, function engine.Match) []engine.Match {
	if name == "" || !function.Exists() {
		return nil
	}
	var reads []engine.Match
	for _, variable := range descendantsOf(function) {
		if variable.Kind() == "Expr_Variable" && variable.Name() == name && variable.Parent().Kind() != "Param" && interactionKind(variable) != Assigned {
			reads = append(reads, variable)
		}
	}

	return reads
}

func isDereferenced(node engine.Match) bool {
	return isChild(node, "Expr_PropertyFetch", "var") || isChild(node, "Expr_MethodCall", "var") || isChild(node, "Expr_ArrayDimFetch", "var")
}

// isNullGuardedUse says whether the use settles or tests the value's nullness: de-nulled, type-checked, gated by a
// question to its owner, or read as a condition.
func isNullGuardedUse(node engine.Match) bool {
	if isDeNulled(node) || isTypeInterrogated(node) || isGatedByOwnerPredicate(node) {
		return true
	}
	parent := node.Parent()
	if isChild(node, "Expr_ArrayDimFetch", "var") || isChild(node, "Expr_Assign", "expr") {
		return isNullGuardedUse(parent)
	}

	return slices.Contains([]string{"Expr_BooleanNot", "Expr_BinaryOp_BooleanAnd", "Expr_BinaryOp_BooleanOr", "Expr_Isset", "Expr_Empty"}, parent.Kind()) ||
		isChild(node, "Expr_AssignOp_Coalesce", "var") || isChild(node, "Stmt_If", "cond") ||
		isChild(node, "Stmt_While", "cond") || isChild(node, "Expr_Ternary", "cond")
}

func isTypeInterrogated(node engine.Match) bool {
	if isChild(node, "Expr_Instanceof", "expr") {
		return true
	}
	call := node.Parent().Parent()

	return node.Parent().Kind() == "Arg" && call.Kind() == "Expr_FuncCall" && isName(call.Child("name")) &&
		slices.Contains(typePredicates, strings.ToLower(call.Child("name").Name()))
}

// isGatedByOwnerPredicate says whether a property read sits inside an `if` whose condition asks its owner a method.
func isGatedByOwnerPredicate(node engine.Match) bool {
	if node.Kind() != "Expr_PropertyFetch" {
		return false
	}
	owner := node.Child("var")
	for at := node.Parent(); at.Exists() && !slices.Contains(functionLikes, at.Kind()); at = at.Parent() {
		if at.Kind() != "Stmt_If" {
			continue
		}
		condition := at.Child("cond")
		for _, asked := range append([]engine.Match{condition}, descendantsOf(condition)...) {
			if asked.Kind() == "Expr_MethodCall" && asked.Child("var").SameSyntax(owner) {
				return true
			}
		}
	}

	return false
}

// isSelfReadGuardedByStateClause says whether a `$this->field` read follows, in its function's own statements, an
// `if` over the object's state that bails out.
func isSelfReadGuardedByStateClause(node engine.Match) bool {
	if node.Kind() != "Expr_PropertyFetch" || !isThis(node.Child("var")) {
		return false
	}
	for _, statement := range bodyOf(enclosingFunction(node)) {
		if contains(statement, node) {
			return false
		}
		if isStateGuardClause(statement) {
			return true
		}
	}

	return false
}

// bodyOf is a function's own statements; an arrow function or an expression-bodied hook has none a guard can lead.
func bodyOf(function engine.Match) []engine.Match {
	var body []engine.Match
	for _, statement := range function.Children() {
		if (statement.Node().Field == "stmts" || statement.Node().Field == "body") && statement.Node().Role == "statement" {
			body = append(body, statement)
		}
	}

	return body
}

func isStateGuardClause(statement engine.Match) bool {
	if statement.Kind() != "Stmt_If" || statement.Child("else").Exists() || statement.Child("elseifs").Exists() {
		return false
	}
	var last engine.Match
	for _, child := range statement.Children() {
		if child.Node().Field == "stmts" {
			last = child
		}
	}
	if !isBailOut(last) {
		return false
	}
	condition := statement.Child("cond")
	for _, read := range append([]engine.Match{condition}, descendantsOf(condition)...) {
		if read.Kind() == "Expr_PropertyFetch" && isThis(read.Child("var")) {
			return true
		}
	}

	return false
}

func isBailOut(statement engine.Match) bool {
	switch statement.Kind() {
	case "Stmt_Return", "Stmt_Continue", "Stmt_Break":
		return true
	case "Stmt_Expression":
		return statement.Child("expr").Kind() == "Expr_Throw"
	}

	return false
}

func isThis(node engine.Match) bool {
	return node.Kind() == "Expr_Variable" && node.Name() == "this"
}

// contains says whether the node is the ancestor or the node itself.
func contains(ancestor, node engine.Match) bool {
	for at := node; at.Exists(); at = at.Parent() {
		if at.Node() == ancestor.Node() {
			return true
		}
	}

	return false
}

// flowClass is the class a function sits in as the value flow reads it: the nearest class around it, interfaces,
// traits and enums passed over; empty inside an anonymous class.
func flowClass(function engine.Match) string {
	for at := function.Parent(); at.Exists(); at = at.Parent() {
		if at.Kind() == "Stmt_Class" {
			return at.Node().Symbol
		}
	}

	return ""
}

// Location is a match as the PHP engine names one: its file and line.
func Location(match engine.Match) string {
	return match.File() + ":" + strconv.Itoa(match.Line())
}
