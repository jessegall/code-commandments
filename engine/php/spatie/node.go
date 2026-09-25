// Package spatie is what the engine knows of spatie/laravel-data and its TypeScript transformer, stated once: which
// classes are Data, how a Data is built and hydrated, and what its fields put on the wire.
package spatie

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

const (
	Data           = `Spatie\LaravelData\Data`
	DataPipe       = `Spatie\LaravelData\DataPipes\DataPipe`
	Cast           = `Spatie\LaravelData\Casts\Cast`
	Optional       = `Spatie\LaravelData\Optional`
	TypeScript     = `Spatie\TypeScriptTransformer\Attributes\TypeScript`
	DataCollection = `Spatie\LaravelData\DataCollection`
	hidden         = "Hidden"
)

var (
	// NoContainerContracts are the Spatie contracts whose implementations the package builds without the container.
	NoContainerContracts         = []string{DataPipe, Cast}
	containerInjectionAttributes = []string{"FromContainer", "FromContainerProperty"}
	nativeCastTypes              = []string{"DateTimeInterface", "DateTime", "DateTimeImmutable", "Carbon", "CarbonImmutable"}
	knownTsTransformers          = []string{"DateTimeInterfaceTransformer", "ArrayableTransformer"}
)

// Node is a match read in spatie/laravel-data's terms.
type Node struct {
	engine.Match
}

// Decorate reads a match as a Node.
func (Node) Decorate(m engine.Match) Node {
	return Node{Match: m}
}

func (n Node) program() *php.Program {
	return php.ProgramOf(n.Codebase())
}

// IsDataClass says whether the node sits in a Data class.
func (n Node) IsDataClass() bool {
	return n.program().Extends(php.EnclosingClassName(n.Match), Data)
}

// InDataScope says whether the node sits in a Data class, or in a trait a Data class uses.
func (n Node) InDataScope() bool {
	if n.IsDataClass() {
		return true
	}
	for _, user := range n.program().UsersOfTrait(php.EnclosingClassName(n.Match)) {
		if n.program().Extends(user, Data) {
			return true
		}
	}

	return false
}

// IsTypeScriptData says whether the node is a Data class marked `#[TypeScript]`.
func (n Node) IsTypeScriptData() bool {
	return n.IsDataClass() && php.HasAttribute(n.Match, "TypeScript")
}

// IsNewData says whether the node constructs a Data with `new`.
func (n Node) IsNewData() bool {
	return n.program().Extends(newClassName(n.Match), Data)
}

// OnDataClass says whether the node is a static call on a Data class.
func (n Node) OnDataClass() bool {
	return n.program().Extends(php.StaticCallClass(n.Match), Data)
}

// IsRichData says whether the node constructs a Data class that does more than map a payload onto its fields.
func (n Node) IsRichData() bool {
	return DataClassShapeOf(n.Codebase()).IsRich(newClassName(n.Match))
}

// IsPageObject says whether the node sits in a Data class that is a page object.
func (n Node) IsPageObject() bool {
	return n.IsDataClass() && IsPageObject(n.Codebase(), php.EnclosingClassName(n.Match))
}

// HasUnhiddenInjectedService says whether the class puts a container-injected service on the wire: a public field
// filled from the container, not hidden, and not itself Data.
func (n Node) HasUnhiddenInjectedService() bool {
	for _, field := range php.Fields(php.EnclosingClass(n.Match)) {
		if field.IsPublic && field.HasAttribute(containerInjectionAttributes...) && !field.HasAttribute(hidden) && !n.typeIsData(field.Type) {
			return true
		}
	}

	return false
}

func (n Node) typeIsData(written *contract.Type) bool {
	class := php.Written(written).Class()

	return class != "" && n.program().Extends(class, Data)
}

// OptionalPublicFieldNames is every public field of the class that may be Optional, once each.
func (n Node) OptionalPublicFieldNames() []string {
	names := []string{}
	for _, field := range php.Fields(php.EnclosingClass(n.Match)) {
		if field.IsPublic && typeIncludesOptional(field.Type) && !slices.Contains(names, field.Name) {
			names = append(names, field.Name)
		}
	}

	return names
}

// EveryConstructorParamOptional says whether the class's constructor promotes at least one field and every one of
// them may be Optional.
func (n Node) EveryConstructorParamOptional() bool {
	var promoted []engine.Match
	for _, param := range php.ConstructorParams(php.EnclosingClass(n.Match)) {
		if slices.Contains(param.Node().Flags, "promoted") {
			promoted = append(promoted, param)
		}
	}
	if len(promoted) == 0 {
		return false
	}
	for _, param := range promoted {
		if !typeIncludesOptional(param.Node().Declared) {
			return false
		}
	}

	return true
}

// HookMissingComputed says whether a Data property's get-only hook lacks `#[Computed]`, so Spatie reads it as input.
func (n Node) HookMissingComputed() bool {
	if n.Kind() != "PropertyHook" || !n.program().Extends(php.EnclosingClassName(n.Match), Data) {
		return false
	}
	property := n.Parent()
	if property.Kind() != "Stmt_Property" || carriesComputed(property) {
		return false
	}
	for _, hook := range property.Children() {
		if hook.Kind() == "PropertyHook" && hook.Name() == "set" {
			return false
		}
	}

	return true
}

func carriesComputed(property engine.Match) bool {
	for _, name := range php.AttributeNames(property) {
		name = strings.TrimLeft(name, `\`)
		if name == "Computed" || strings.HasSuffix(name, `\Computed`) {
			return true
		}
	}

	return false
}

// PageObjectMissingTypeScript says whether the node sits in a page object that is not marked `#[TypeScript]`.
func (n Node) PageObjectMissingTypeScript() bool {
	class := php.EnclosingClass(n.Match)

	return class.Exists() && n.IsPageObject() && !classHasAttribute(class, "TypeScript")
}

// NestedWireTypeMissingTypeScript says whether a `#[TypeScript]` Data class puts on the wire a field whose Data type
// is not itself marked `#[TypeScript]`, leaving a hole in the generated types.
func (n Node) NestedWireTypeMissingTypeScript() bool {
	class := php.EnclosingClass(n.Match)
	if !class.Exists() || !n.program().Extends(php.EnclosingClassName(n.Match), Data) || !classHasAttribute(class, "TypeScript") {
		return false
	}
	field, ok := php.AsField(n.Match)
	if !ok || !field.IsPublic || field.HasAttribute(hidden) || field.HasAttribute("TypeScriptType", "LiteralTypeScriptType") {
		return false
	}
	nested := n.NestedWireTypeFqcn()
	if nested == "" || !n.program().Extends(nested, Data) {
		return false
	}
	declaration, ok := n.program().Declaration(nested)

	return ok && !classHasAttribute(declaration, "TypeScript")
}

// NestedWireTypeFqcn is the one class a field puts on the wire: its `#[DataCollectionOf]` element, or its type.
func (n Node) NestedWireTypeFqcn() string {
	if n.Kind() != "Param" && n.Kind() != "Stmt_Property" {
		return ""
	}
	if element := dataCollectionOfElement(n.Match); element != "" {
		return element
	}

	return soleClassNameOfType(n.Node().Declared)
}

func dataCollectionOfElement(node engine.Match) string {
	for _, attribute := range attributes(node) {
		name := strings.TrimLeft(attribute.Child("name").Name(), `\`)
		if name != "DataCollectionOf" && !strings.HasSuffix(name, `\DataCollectionOf`) {
			continue
		}
		if value := firstArgument(attribute).Child("value"); value.Kind() == "Expr_ClassConstFetch" && isName(value.Child("class")) {
			return strings.TrimLeft(value.Child("class").Name(), `\`)
		}
	}

	return ""
}

func soleClassNameOfType(written *contract.Type) string {
	name := php.Written(written)
	switch {
	case written == nil:
		return ""
	case written.Kind == "union":
		var classes []string
		for _, member := range written.Members {
			for _, class := range php.Written(member).Names() {
				if class = strings.TrimLeft(class, `\`); strings.ToLower(class) != "null" && class != Optional {
					classes = append(classes, class)
				}
			}
		}
		if len(classes) == 1 {
			return classes[0]
		}

		return ""
	case len(name.Names()) == 1 && written.Kind != "intersection":
		return strings.TrimLeft(name.Names()[0], `\`)
	}

	return ""
}

// PropertyTypedAsDataCollection says whether a Data class's field is typed as the legacy DataCollection.
func (n Node) PropertyTypedAsDataCollection() bool {
	if !n.program().Extends(php.EnclosingClassName(n.Match), Data) || (n.Kind() != "Param" && n.Kind() != "Stmt_Property") {
		return false
	}
	written := n.Node().Declared
	if written == nil {
		return false
	}
	if written.Kind == "union" {
		for _, member := range written.Members {
			if IsDataCollectionName(member) {
				return true
			}
		}

		return false
	}

	return IsDataCollectionName(written)
}

// IsDataCollectionName says whether a written type names the legacy DataCollection.
func IsDataCollectionName(written *contract.Type) bool {
	names := php.Written(written).Names()

	return written != nil && written.Kind != "union" && written.Kind != "intersection" && len(names) == 1 && strings.TrimLeft(names[0], `\`) == DataCollection
}

// NullableWireObject says whether a `#[TypeScript]` Data class puts on the wire a nullable Data or enum field, which
// the generated type cannot tell from a missing one.
func (n Node) NullableWireObject() bool {
	class := php.EnclosingClass(n.Match)
	if !class.Exists() || !n.program().Extends(php.EnclosingClassName(n.Match), Data) || !classHasAttribute(class, "TypeScript") {
		return false
	}
	if n.isHookedProperty() || (n.Kind() != "Param" && n.Kind() != "Stmt_Property") {
		return false
	}
	inner := nullableClassName(n.Node().Declared)

	return inner != "" && (n.program().Extends(inner, Data) || n.program().IsEnum(inner))
}

func (n Node) isHookedProperty() bool {
	if n.Kind() != "Stmt_Property" && n.Kind() != "Param" {
		return false
	}
	for _, hook := range n.Children() {
		if hook.Kind() == "PropertyHook" {
			return true
		}
	}

	return false
}

func classHasAttribute(class engine.Match, short string) bool {
	for _, name := range php.AttributeNames(class) {
		name = strings.TrimLeft(name, `\`)
		if name == short || strings.HasSuffix(name, `\`+short) {
			return true
		}
	}

	return false
}

// nullableClassName is the class a nullable type holds: `?X`, or a union of one class and null.
func nullableClassName(written *contract.Type) string {
	name := php.Written(written)
	if written == nil {
		return ""
	}
	if written.Kind != "union" {
		if name.IsNullable() && len(name.Names()) == 1 {
			return strings.TrimLeft(name.Names()[0], `\`)
		}

		return ""
	}
	var classes []string
	nullable := false
	for _, member := range written.Members {
		switch {
		case strings.EqualFold(member.Name, "null") && (member.Kind == "keyword" || member.Kind == "named"):
			nullable = true
		case len(php.Written(member).Names()) == 1 && member.Kind != "intersection":
			classes = append(classes, strings.TrimLeft(member.Name, `\`))
		}
	}
	if nullable && len(classes) == 1 {
		return classes[0]
	}

	return ""
}

func typeIncludesOptional(written *contract.Type) bool {
	if written == nil || written.Kind != "union" {
		return false
	}
	for _, member := range written.Members {
		if names := php.Written(member).Names(); member.Kind != "intersection" && len(names) == 1 && strings.TrimLeft(names[0], `\`) == Optional {
			return true
		}
	}

	return false
}

// newClassName is the class a `new` names as written.
func newClassName(node engine.Match) string {
	if class := node.Child("class"); node.Kind() == "Expr_New" && isName(class) {
		return class.Name()
	}

	return ""
}
