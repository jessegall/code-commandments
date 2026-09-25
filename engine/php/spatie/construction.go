package spatie

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// castAttributes are the attributes that give a Data field a cast of its own.
var castAttributes = []string{"WithCast", "WithCastAndTransformer", "WithCastable"}

// loops are the statement kinds that repeat their body.
var loops = []string{"Stmt_Foreach", "Stmt_For", "Stmt_While", "Stmt_Do"}

// HydrationSlot is the Data field an argument's keyed value lands in: `Order::from(['customer' => …])`.
type HydrationSlot struct {
	Owner        string
	Property     string
	DeclaredType string
	IsCollection bool
	ElementType  string
	ValueInList  bool
	DestHasCast  bool
}

// FactoryRef is the static factory an `array_map` calls per item.
type FactoryRef struct {
	Class             string
	Method            string
	ReturnsType       string
	ClosesOverContext bool
}

func (n Node) types() *php.Types {
	return php.TypesOf(n.Codebase())
}

// IsHandedConstructedData says whether a `new` of a Data class hands one of its fields a value that is already of
// the field's class, rather than letting the Data build it.
func (n Node) IsHandedConstructedData() bool {
	if n.Kind() != "Expr_New" {
		return false
	}
	fqcn := newClassName(n.Match)
	class, ok := DataClassShapeOf(n.Codebase()).ClassFor(fqcn)
	function := enclosingFunction(n.Match)
	if !ok || !function.Exists() {
		return false
	}
	params := php.ConstructorParams(class)
	index := 0
	for _, argument := range n.Children() {
		if argument.Node().Field != "args" {
			continue
		}
		if argument.Kind() == "Arg" && n.argumentIsAlready(argument, index, fqcn, params, function) {
			return true
		}
		index++
	}

	return false
}

func (n Node) argumentIsAlready(argument engine.Match, index int, fqcn string, params []engine.Match, function engine.Match) bool {
	name := argument.Child("name").Name()
	if !argument.Child("name").Exists() {
		name = promotedParamName(params, index)
	}
	declared := ""
	if name != "" {
		declared = n.types().PropertyTypeOf(fqcn, name)
	}
	if !php.IsClassName(declared) || constructsInPlace(argument.Child("value"), declared) {
		return false
	}
	actual := n.types().TypeIn(argument.Child("value"), function, php.EnclosingClassName(n.Match))

	return actual != "" && strings.TrimLeft(actual, `\`) == strings.TrimLeft(declared, `\`)
}

func promotedParamName(params []engine.Match, index int) string {
	if index >= len(params) {
		return ""
	}
	param := params[index].Node()
	variable := params[index].Child("var")
	if !slices.Contains(param.Flags, "promoted") || slices.Contains(param.Flags, "variadic") || variable.Kind() != "Expr_Variable" {
		return ""
	}

	return variable.Name()
}

func constructsInPlace(value engine.Match, declared string) bool {
	if value.Kind() == "Expr_New" {
		return true
	}
	class := value.Child("class")

	return value.Kind() == "Expr_StaticCall" && isName(class) && strings.TrimLeft(class.Name(), `\`) == strings.TrimLeft(declared, `\`)
}

// AlwaysHandBuiltAtConstruction says whether every `::from` of the field's Data class hands the field a value built
// by hand, where a cast could build it.
func (n Node) AlwaysHandBuiltAtConstruction() bool {
	field, ok := php.AsField(n.Match)
	if !ok {
		return false
	}
	data := php.EnclosingClassName(n.Match)
	if !field.IsPublic || data == "" || !n.IsDataClass() || field.HasAttribute(castAttributes...) {
		return false
	}
	class := php.Written(field.Type).Class()
	if class == "" || n.program().Extends(class, Data) || n.castsNatively(class) {
		return false
	}

	return DataConstructionsOf(n.Codebase()).AlwaysHandBuilt(data, field.Name, class)
}

func (n Node) castsNatively(class string) bool {
	return n.program().IsEnum(class) || slices.Contains(nativeCastTypes, php.ShortName(class)) || n.program().Implements(class, "DateTimeInterface")
}

// CastsNativelyPublic says whether Spatie casts a value of the class without being told: an enum or a date.
func (n Node) CastsNativelyPublic(class string) bool {
	return n.castsNatively(class)
}

// IsPerItemHydration says whether the node runs once per item: in a loop, or in an `array_map` callback.
func (n Node) IsPerItemHydration() bool {
	return within(n.Match, isLoop) || n.isWithinArrayMap()
}

func (n Node) isWithinArrayMap() bool {
	for at := n.Parent(); at.Exists(); at = at.Parent() {
		if at.Kind() == "Expr_Closure" || at.Kind() == "Expr_ArrowFunction" {
			if isArrayMapArgument(at.Parent()) {
				return true
			}

			break
		}
	}

	return isArrayMapArgument(n.Parent())
}

func isArrayMapArgument(node engine.Match) bool {
	call := node.Parent()

	return node.Kind() == "Arg" && call.Kind() == "Expr_FuncCall" && isName(call.Child("name")) && call.Child("name").Name() == "array_map"
}

// IsInlineProjection says whether a static call is handed an array literal as its first argument.
func (n Node) IsInlineProjection() bool {
	first := firstArgument(n.Match)

	return n.Kind() == "Expr_StaticCall" && first.Kind() == "Arg" && first.Child("value").Kind() == "Expr_Array"
}

// IsConditionalConstruction says whether, within its loop, the node runs only on some items: under a branch, or in a
// callback other than `array_map`'s.
func (n Node) IsConditionalConstruction() bool {
	loop := n.Parent()
	for loop.Exists() && !isLoop(loop) {
		loop = loop.Parent()
	}
	if !loop.Exists() {
		return false
	}
	for at := n.Parent(); at.Exists() && at.Node() != loop.Node(); at = at.Parent() {
		switch at.Kind() {
		case "Stmt_If", "Stmt_Else", "Stmt_ElseIf", "Expr_Match", "Expr_Ternary":
			return true
		case "Expr_Closure", "Expr_ArrowFunction":
			if !isArrayMapArgument(at.Parent()) {
				return true
			}
		}
	}

	return false
}

// IsWithinTolerantCatch says whether the node sits in a try, inside a loop, whose catch skips the item.
func (n Node) IsWithinTolerantCatch() bool {
	try := n.Parent()
	for try.Exists() && try.Kind() != "Stmt_TryCatch" {
		try = try.Parent()
	}
	if !try.Exists() || !within(try, isLoop) {
		return false
	}
	for _, catch := range try.Children() {
		if catch.Kind() != "Stmt_Catch" {
			continue
		}
		for _, statement := range catch.Children() {
			if statement.Node().Field != "stmts" {
				continue
			}
			for _, node := range append([]engine.Match{statement}, descendants(statement)...) {
				if node.Kind() == "Stmt_Continue" || node.Kind() == "Stmt_Return" {
					return true
				}
			}
		}
	}

	return false
}

// IsKeyedMapAssignment says whether the node is assigned into a keyed array slot: `$map[$key] = …`.
func (n Node) IsKeyedMapAssignment() bool {
	assign := n.Parent()
	for assign.Exists() && assign.Kind() != "Expr_Assign" {
		assign = assign.Parent()
	}
	target := assign.Child("var")

	return assign.Exists() && target.Kind() == "Expr_ArrayDimFetch" && target.Child("dim").Exists()
}

// HydrationSlot is the Data field the node's keyed value hydrates: the node a value under a string key, alone or in a
// list, of the one array a Data's `::from` is handed.
func (n Node) HydrationSlot() (HydrationSlot, bool) {
	key, item, inList, ok := keyedHydrationItem(n.Match)
	if !ok {
		return HydrationSlot{}, false
	}
	call, ok := n.soleFromCallOf(item)
	if !ok {
		return HydrationSlot{}, false
	}
	owner := constructedFrom(call)
	if owner == "" || !n.program().Extends(owner, Data) {
		return HydrationSlot{}, false
	}
	declared := n.types().PropertyTypeOf(owner, key)
	element := n.types().CollectionElementOf(owner, key)
	slot := HydrationSlot{Owner: owner, Property: key, DeclaredType: declared, IsCollection: element != "", ElementType: element, ValueInList: inList, DestHasCast: n.propertyHasCast(owner, key)}
	if element == "" {
		slot.ElementType = declared
	}

	return slot, true
}

// keyedHydrationItem is the string key the node's value sits under, climbing through at most one list around it.
func keyedHydrationItem(node engine.Match) (key string, item engine.Match, inList, ok bool) {
	crossed := 0
	for current := node; ; {
		parent := current.Parent()
		switch parent.Kind() {
		case "ArrayItem":
			if written := parent.Child("key"); written.Kind() == "Scalar_String" {
				key, _ = written.Node().Value.Text()

				return key, parent, crossed >= 1, true
			}
			current = parent
		case "Expr_Array":
			if crossed++; crossed > 1 {
				return "", engine.Match{}, false, false
			}
			current = parent
		default:
			return "", engine.Match{}, false, false
		}
	}
}

func (n Node) soleFromCallOf(item engine.Match) (engine.Match, bool) {
	array := item.Parent()
	if array.Kind() != "Expr_Array" {
		return engine.Match{}, false
	}
	parent := array.Parent()
	switch {
	case parent.Kind() == "Arg":
		return fromCallOwningArgument(parent)
	case parent.Kind() == "Expr_Assign" && parent.Child("var").Kind() == "Expr_Variable" && parent.Child("var").Name() != "":
		return n.fromCallForLocal(parent.Child("var").Name())
	}

	return engine.Match{}, false
}

func fromCallOwningArgument(argument engine.Match) (engine.Match, bool) {
	call := argument.Parent()
	if call.Kind() != "Expr_StaticCall" || call.Child("name").Kind() != "Identifier" || call.Child("name").Name() != "from" {
		return engine.Match{}, false
	}
	arguments := php.Arguments(call)

	return call, len(arguments) == 1 && arguments[0].Node() == argument.Node()
}

func (n Node) fromCallForLocal(variable string) (engine.Match, bool) {
	function := enclosingFunction(n.Match)
	if !function.Exists() || assignmentCount(function, variable) != 1 {
		return engine.Match{}, false
	}
	for _, call := range descendants(function) {
		if call.Kind() == "Expr_StaticCall" && call.Child("name").Kind() == "Identifier" && call.Child("name").Name() == "from" && soleArgumentIsVariable(call, variable) {
			return call, true
		}
	}

	return engine.Match{}, false
}

func assignmentCount(function engine.Match, variable string) int {
	count := 0
	for _, assign := range descendants(function) {
		if target := assign.Child("var"); assign.Kind() == "Expr_Assign" && target.Kind() == "Expr_Variable" && target.Name() == variable {
			count++
		}
	}

	return count
}

func soleArgumentIsVariable(call engine.Match, variable string) bool {
	arguments := php.Arguments(call)
	value := engine.Match{}
	if len(arguments) == 1 {
		value = arguments[0].Child("value")
	}

	return len(arguments) == 1 && value.Kind() == "Expr_Variable" && value.Name() == variable
}

// constructedFrom is the class a static call names, `self` and `static` read as the class they sit in.
func constructedFrom(call engine.Match) string {
	class := call.Child("class")
	if !isName(class) {
		return ""
	}
	if class.Name() == "self" || class.Name() == "static" {
		return php.EnclosingClassName(call)
	}

	return strings.TrimLeft(class.Name(), `\`)
}

func (n Node) propertyHasCast(owner, property string) bool {
	class, ok := n.program().Class(owner)
	if !ok {
		return false
	}
	for _, field := range php.Fields(class) {
		if field.Name == property {
			return field.HasAttribute(castAttributes...)
		}
	}

	return false
}

// IsEnumUnwrapIntoItsOwnSlot says whether an enum's `->value` is handed to a Data slot typed as that same enum, which
// Spatie would have cast from the enum itself.
func (n Node) IsEnumUnwrapIntoItsOwnSlot() bool {
	if n.Kind() != "Expr_PropertyFetch" && n.Kind() != "Expr_NullsafePropertyFetch" {
		return false
	}
	function := enclosingFunction(n.Match)
	if !function.Exists() {
		return false
	}
	enum := n.types().TypeIn(n.Child("var"), function, php.EnclosingClassName(n.Match))
	if enum == "" || !n.program().IsEnum(enum) {
		return false
	}
	slotType := ""
	if slot, ok := n.HydrationSlot(); ok {
		slotType = slot.ElementType
	}
	if slotType == "" {
		slotType = n.forwardedEnumSlotType()
	}

	return slotType != "" && strings.TrimLeft(slotType, `\`) == strings.TrimLeft(enum, `\`)
}

// forwardedEnumSlotType is the slot type a keyed value reaches through a method that forwards its array parameter
// to a Data's `::from`.
func (n Node) forwardedEnumSlotType() string {
	key, item, _, ok := keyedHydrationItem(n.Match)
	argument := item.Parent().Parent()
	if !ok || item.Parent().Kind() != "Expr_Array" || argument.Kind() != "Arg" {
		return ""
	}
	callee, owner := n.resolveCallee(argument.Parent())
	if !callee.Exists() {
		return ""
	}
	params := php.Params(callee)
	position := argPosition(argument)
	if position >= len(params) {
		return ""
	}
	variable := params[position].Child("var")
	if variable.Kind() != "Expr_Variable" || variable.Name() == "" {
		return ""
	}
	for _, from := range descendants(callee) {
		if from.Kind() != "Expr_StaticCall" || from.Child("name").Kind() != "Identifier" || from.Child("name").Name() != "from" || !soleArgumentIsVariable(from, variable.Name()) {
			continue
		}
		if built := constructedFromInClass(from, owner); built != "" && n.program().Extends(built, Data) {
			return n.types().PropertyTypeOf(built, key)
		}
	}

	return ""
}

func (n Node) resolveCallee(call engine.Match) (engine.Match, string) {
	function := enclosingFunction(n.Match)
	if !function.Exists() {
		return engine.Match{}, ""
	}
	owner := ""
	name := call.Child("name")
	switch {
	case call.Kind() == "Expr_MethodCall" && name.Kind() == "Identifier":
		owner = n.types().TypeIn(call.Child("var"), function, php.EnclosingClassName(n.Match))
	case call.Kind() == "Expr_StaticCall" && isName(call.Child("class")) && name.Kind() == "Identifier":
		owner = strings.TrimLeft(call.Child("class").Name(), `\`)
		if slices.Contains([]string{"self", "static", "parent"}, owner) && php.EnclosingClassName(n.Match) != "" {
			owner = strings.TrimLeft(php.EnclosingClassName(n.Match), `\`)
		}
	}
	if owner == "" {
		return engine.Match{}, ""
	}
	class, ok := n.program().Class(owner)
	if !ok {
		return engine.Match{}, ""
	}
	method, _ := php.Method(class, name.Name())

	return method, owner
}

func argPosition(argument engine.Match) int {
	call := argument.Parent()
	if call.Kind() != "Expr_MethodCall" && call.Kind() != "Expr_StaticCall" {
		return 0
	}
	for position, candidate := range php.Arguments(call) {
		if candidate.Node() == argument.Node() {
			return position
		}
	}

	return 0
}

func constructedFromInClass(call engine.Match, owner string) string {
	class := call.Child("class")
	if !isName(class) {
		return ""
	}
	if class.Name() == "self" || class.Name() == "static" {
		return owner
	}

	return strings.TrimLeft(class.Name(), `\`)
}

// FromArgIsArrayLiteral says whether the call's one argument is an array literal.
func (n Node) FromArgIsArrayLiteral() bool {
	arguments := php.Arguments(n.Match)

	return len(arguments) == 1 && arguments[0].Child("value").Kind() == "Expr_Array"
}

// ConstructedClass is the class a static call constructs.
func (n Node) ConstructedClass() string {
	if n.Kind() != "Expr_StaticCall" {
		return ""
	}

	return constructedFrom(n.Match)
}

// HydratesAnAutoBuiltSlot says whether a static call builds, by hand, exactly the value its hydration slot would
// build itself from the raw input.
func (n Node) HydratesAnAutoBuiltSlot() bool {
	slot, ok := n.HydrationSlot()

	return ok && slot.ValueInList == slot.IsCollection && slot.ElementType != "" && slot.ElementType == n.ConstructedClass()
}

// HydrationSlotHasCast says whether the node's hydration slot carries a cast of its own.
func (n Node) HydrationSlotHasCast() bool {
	slot, ok := n.HydrationSlot()

	return ok && slot.DestHasCast
}

// MappedFactory is the static factory an `array_map` calls per item, as a first-class callable or the one call its
// callback makes.
func (n Node) MappedFactory() (FactoryRef, bool) {
	arguments := php.Arguments(n.Match)
	if callName(n.Match) != "array_map" || len(arguments) == 0 || !arguments[0].Child("value").Exists() {
		return FactoryRef{}, false
	}
	call, closesOver := factoryCallOf(arguments[0].Child("value"))
	class, name := call.Child("class"), call.Child("name")
	if call.Kind() != "Expr_StaticCall" || !isName(class) || name.Kind() != "Identifier" {
		return FactoryRef{}, false
	}
	owner := strings.TrimLeft(class.Name(), `\`)
	if class.Name() == "self" || class.Name() == "static" {
		owner = class.Name()
		if enclosing := php.EnclosingClassName(n.Match); enclosing != "" {
			owner = enclosing
		}
	}
	returns := ""
	if function := enclosingFunction(n.Match); function.Exists() {
		returns = n.types().TypeIn(call, function, php.EnclosingClassName(n.Match))
	}

	return FactoryRef{Class: owner, Method: name.Name(), ReturnsType: returns, ClosesOverContext: closesOver}, true
}

// MappedFactoryDerivesElement says whether an `array_map` calls a Data's own factory to build each element of a
// collection slot that declares that element, so the slot could build them itself.
func (n Node) MappedFactoryDerivesElement() bool {
	factory, ok := n.MappedFactory()
	if !ok || factory.Method == "from" || factory.Method == "collect" || factory.ClosesOverContext || !n.program().Extends(factory.Class, Data) {
		return false
	}
	slot, ok := n.HydrationSlot()

	return ok && slot.IsCollection && slot.ElementType != "" && factory.ReturnsType == slot.ElementType
}

func factoryCallOf(callable engine.Match) (engine.Match, bool) {
	switch callable.Kind() {
	case "Expr_StaticCall":
		return callable, false
	case "Expr_ArrowFunction":
		if body := callable.Child("expr"); body.Kind() == "Expr_StaticCall" {
			return body, referencesBeyond(body, callable)
		}
	case "Expr_Closure":
		for _, use := range callable.Children() {
			if use.Node().Field == "uses" {
				return engine.Match{}, true
			}
		}
		if returned, ok := soleReturn(callable); ok && returned.Kind() == "Expr_StaticCall" {
			return returned, referencesBeyond(returned, callable)
		}
	}

	return engine.Match{}, false
}

// referencesBeyond says whether the call reads a variable its callback does not take as a parameter.
func referencesBeyond(call, callback engine.Match) bool {
	var params []string
	for _, param := range php.Params(callback) {
		if variable := param.Child("var"); variable.Kind() == "Expr_Variable" && variable.Name() != "" {
			params = append(params, variable.Name())
		}
	}
	for _, variable := range append([]engine.Match{call}, descendants(call)...) {
		if variable.Kind() == "Expr_Variable" && variable.Name() != "" && !slices.Contains(params, variable.Name()) {
			return true
		}
	}

	return false
}

func soleReturn(closure engine.Match) (engine.Match, bool) {
	var returns []engine.Match
	for _, statement := range closure.Children() {
		if statement.Node().Field != "stmts" {
			continue
		}
		for _, node := range append([]engine.Match{statement}, descendants(statement)...) {
			if node.Kind() == "Stmt_Return" {
				returns = append(returns, node)
			}
		}
	}
	if len(returns) != 1 {
		return engine.Match{}, false
	}

	return returns[0].Child("expr"), true
}

// ConstructsNativeCastValue says whether the node builds a value Spatie casts itself: `Enum::from`, a date's `parse`,
// or a `new` of an enum or date.
func (n Node) ConstructsNativeCastValue() bool {
	switch staticCallMethod(n.Match) {
	case "from":
		class := php.StaticCallClass(n.Match)

		return class != "" && n.program().IsEnum(strings.TrimLeft(class, `\`))
	case "parse":
		class := php.StaticCallClass(n.Match)

		return class != "" && slices.Contains(nativeCastTypes, php.ShortName(class))
	}
	built := newClassName(n.Match)

	return built != "" && n.castsNatively(strings.TrimLeft(built, `\`))
}

// HasSingleArgument says whether the call passes exactly one argument.
func (n Node) HasSingleArgument() bool {
	return len(php.Arguments(n.Match)) == 1
}

// SlotAcceptsNativeCast says whether the node's hydration slot is typed as what the node builds, and Spatie would cast
// it: the same enum, or a date.
func (n Node) SlotAcceptsNativeCast() bool {
	slot, ok := n.HydrationSlot()
	if !ok || slot.DeclaredType == "" {
		return false
	}
	enum := ""
	if staticCallMethod(n.Match) == "from" {
		enum = strings.TrimLeft(php.StaticCallClass(n.Match), `\`)
	}
	if enum != "" && n.program().IsEnum(enum) {
		return slot.DeclaredType == enum
	}

	return n.castsNatively(slot.DeclaredType) && !n.program().IsEnum(slot.DeclaredType)
}

// IsHandKeyRemap says whether a Data's `::from([...])` only renames snake_case keys of one source array to the
// camelCase fields, which a name mapper does.
func (n Node) IsHandKeyRemap() bool {
	if staticCallMethod(n.Match) != "from" || !n.OnDataClass() || !n.FromArgIsArrayLiteral() {
		return false
	}
	var items []engine.Match
	for _, item := range php.Arguments(n.Match)[0].Child("value").Children() {
		if item.Node().Field == "items" {
			items = append(items, item)
		}
	}
	if len(items) < 2 {
		return false
	}
	source := ""
	renamed := false
	for _, item := range items {
		key, value := item.Child("key"), item.Child("value")
		if item.Kind() != "ArrayItem" || key.Kind() != "Scalar_String" || value.Kind() != "Expr_ArrayDimFetch" ||
			value.Child("var").Kind() != "Expr_Variable" || value.Child("var").Name() == "" || value.Child("dim").Kind() != "Scalar_String" {
			return false
		}
		if source == "" {
			source = value.Child("var").Name()
		}
		field, _ := key.Node().Value.Text()
		fetched, _ := value.Child("dim").Node().Value.Text()
		if value.Child("var").Name() != source || snake(field) != fetched {
			return false
		}
		renamed = renamed || field != fetched
	}

	return renamed
}

// snake is a camelCase name in snake_case: an underscore before every capital but a leading one.
func snake(name string) string {
	var out strings.Builder
	for i, letter := range name {
		if i > 0 && letter >= 'A' && letter <= 'Z' {
			out.WriteByte('_')
		}
		out.WriteRune(letter)
	}

	return strings.ToLower(out.String())
}

// IsRedundantToArrayRoundtrip says whether a Data's `->toArray()` is handed to a slot typed as that same Data, which
// would take the Data itself.
func (n Node) IsRedundantToArrayRoundtrip() bool {
	if n.Kind() != "Expr_MethodCall" || n.Child("name").Kind() != "Identifier" || n.Child("name").Name() != "toArray" {
		return false
	}
	function := enclosingFunction(n.Match)
	if !function.Exists() {
		return false
	}
	receiver := n.types().TypeIn(n.Child("var"), function, php.EnclosingClassName(n.Match))
	if receiver == "" || !n.program().Extends(receiver, Data) {
		return false
	}
	slot, ok := n.HydrationSlot()

	return ok && slot.ElementType == receiver
}

// DataConstructions is every `::from` of a Data class, by the class it builds.
type DataConstructions struct {
	program *php.Program
	types   *php.Types
	sites   map[string][]engine.Match
}

var dataConstructions = php.Memoised(readDataConstructions)

// DataConstructionsOf is the codebase's Data constructions.
func DataConstructionsOf(codebase *engine.Codebase) *DataConstructions {
	return dataConstructions.Of(codebase)
}

func readDataConstructions(codebase *engine.Codebase) *DataConstructions {
	read := &DataConstructions{program: php.ProgramOf(codebase), types: php.TypesOf(codebase), sites: map[string][]engine.Match{}}
	for _, file := range codebase.Of(contract.PHP).Files() {
		for _, node := range file.Nodes() {
			call := file.Match(node.ID)
			if node.Kind != "Expr_StaticCall" || staticCallMethod(call) != "from" {
				continue
			}
			class := php.StaticCallClass(call)
			if class == "self" || class == "static" {
				class = php.EnclosingClassName(call)
			}
			if class != "" && read.program.Extends(class, Data) {
				read.sites[class] = append(read.sites[class], call)
			}
		}
	}

	return read
}

// AlwaysHandBuilt says whether every `::from` of the class hands the property a value built by hand as the type: a
// construction of it, a factory returning it, or an array read off one object.
func (d *DataConstructions) AlwaysHandBuilt(fqcn, property, class string) bool {
	sites := d.sites[strings.TrimLeft(fqcn, `\`)]
	if len(sites) == 0 {
		return false
	}
	for _, site := range sites {
		value, ok := d.sourceValueFor(site, property)
		if !ok || !d.isHandBuilt(value, site, class) {
			return false
		}
	}

	return true
}

func (d *DataConstructions) sourceValueFor(site engine.Match, property string) (engine.Match, bool) {
	arguments := php.Arguments(site)
	if len(arguments) == 0 {
		return engine.Match{}, false
	}
	array := arguments[0].Child("value")
	if array.Kind() == "Expr_Variable" {
		array = resolveLocal(array, site)
	}
	if array.Kind() != "Expr_Array" {
		return engine.Match{}, false
	}
	for _, item := range array.Children() {
		if key := item.Child("key"); item.Node().Field == "items" && key.Kind() == "Scalar_String" {
			if written, _ := key.Node().Value.Text(); written == property {
				return item.Child("value"), true
			}
		}
	}

	return engine.Match{}, false
}

func (d *DataConstructions) isHandBuilt(value, site engine.Match, class string) bool {
	value = resolveLocal(value, site)
	switch value.Kind() {
	case "Expr_New":
		return isName(value.Child("class")) && d.producesType(value.Child("class").Name(), class)
	case "Expr_StaticCall":
		if !isName(value.Child("class")) {
			return false
		}
		if d.producesType(value.Child("class").Name(), class) {
			return true
		}
		returned := d.types.TypeIn(value, enclosingFunction(site), php.EnclosingClassName(site))

		return returned != "" && d.producesType(returned, class)
	case "Expr_Array":
		_, shared := php.SharedFetchReceiver(value)

		return shared
	}

	return false
}

func (d *DataConstructions) producesType(class, of string) bool {
	class, of = strings.TrimLeft(class, `\`), strings.TrimLeft(of, `\`)

	return class == of || d.program.Extends(class, of)
}

// resolveLocal is what a variable was last assigned in the site's function; anything else as it is.
func resolveLocal(expr, site engine.Match) engine.Match {
	if expr.Kind() != "Expr_Variable" || expr.Name() == "" {
		return expr
	}
	resolved := expr
	function := enclosingFunction(site)
	if !function.Exists() {
		return resolved
	}
	for _, assign := range descendants(function) {
		if target := assign.Child("var"); assign.Kind() == "Expr_Assign" && target.Kind() == "Expr_Variable" && target.Name() == expr.Name() {
			resolved = assign.Child("expr")
		}
	}

	return resolved
}

func staticCallMethod(node engine.Match) string {
	if name := node.Child("name"); node.Kind() == "Expr_StaticCall" && name.Kind() == "Identifier" {
		return name.Name()
	}

	return ""
}

func callName(node engine.Match) string {
	name := node.Child("name")
	switch node.Kind() {
	case "Expr_MethodCall", "Expr_NullsafeMethodCall", "Expr_StaticCall":
		if name.Kind() == "Identifier" {
			return name.Name()
		}
	case "Expr_FuncCall":
		if isName(name) {
			return name.Name()
		}
	}

	return ""
}

func isLoop(node engine.Match) bool {
	return slices.Contains(loops, node.Kind())
}

// within says whether an ancestor of the node passes the test.
func within(node engine.Match, test func(engine.Match) bool) bool {
	for at := node.Parent(); at.Exists(); at = at.Parent() {
		if test(at) {
			return true
		}
	}

	return false
}

// enclosingFunction is the function-like the node is, or the nearest one around it.
func enclosingFunction(node engine.Match) engine.Match {
	for at := node; at.Exists(); at = at.Parent() {
		if slices.Contains([]string{"Stmt_ClassMethod", "Stmt_Function", "Expr_Closure", "Expr_ArrowFunction", "PropertyHook"}, at.Kind()) {
			return at
		}
	}

	return engine.Match{}
}

func descendants(node engine.Match) []engine.Match {
	var all []engine.Match
	for _, child := range node.Children() {
		all = append(all, child)
		all = append(all, descendants(child)...)
	}

	return all
}
