// Package spatie is what the engine knows of spatie/laravel-data and its TypeScript transformer, stated once: which classes are Data, how a Data is built and hydrated, and what its fields put on the wire.
package spatie

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

const (
	Data                 = `Spatie\LaravelData\Data`
	InjectsPropertyValue = `Spatie\LaravelData\Attributes\InjectsPropertyValue`
	DataPipe             = `Spatie\LaravelData\DataPipes\DataPipe`
	Cast                 = `Spatie\LaravelData\Casts\Cast`
	Optional             = `Spatie\LaravelData\Optional`
	TypeScript           = `Spatie\TypeScriptTransformer\Attributes\TypeScript`
	DataCollection       = `Spatie\LaravelData\DataCollection`
	hidden               = "Hidden"
)

var (
	NoContainerContracts = []string{DataPipe, Cast}
	nativeCastTypes      = []string{"DateTimeInterface", "DateTime", "DateTimeImmutable", "Carbon", "CarbonImmutable"}
	knownTsTransformers  = []string{"DateTimeInterfaceTransformer", "ArrayableTransformer"}
)

// Node is a match read in spatie/laravel-data's terms.
type Node struct {
	engine.Match
}

func (Node) Decorate(m engine.Match) Node {
	return Node{Match: m}
}

func (n Node) program() *php.Program {
	return php.ProgramOf(n.Codebase())
}

func (n Node) IsDataClass() bool {
	return n.program().Extends(php.EnclosingClassName(n.Match), Data)
}

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

func (n Node) IsTypeScriptData() bool {
	return n.IsDataClass() && php.HasAttribute(n.Match, "TypeScript")
}

func (n Node) IsNewData() bool {
	return n.program().Extends(newClassName(n.Match), Data)
}

func (n Node) OnDataClass() bool {
	return n.program().Extends(php.StaticCallClass(n.Match), Data)
}

func (n Node) IsRichData() bool {
	return DataClassShapeOf(n.Codebase()).IsRich(newClassName(n.Match))
}

func (n Node) IsPageObject() bool {
	return n.IsDataClass() && IsPageObject(n.Codebase(), php.EnclosingClassName(n.Match))
}

func (n Node) HasUnhiddenInjectedService() bool {
	for _, field := range php.Fields(php.EnclosingClass(n.Match)) {
		if field.IsPublic && php.HasDataInjection(field.Declaration) && !field.HasAttribute(hidden) && !n.typeIsData(field.Type) {
			return true
		}
	}

	return false
}

func (n Node) typeIsData(written *contract.Type) bool {
	class := php.Written(written).Class()

	return class != "" && n.program().Extends(class, Data)
}

func (n Node) OptionalPublicFieldNames() []string {
	names := []string{}
	for _, field := range php.Fields(php.EnclosingClass(n.Match)) {
		if field.IsPublic && typeIncludesOptional(field.Type) && !slices.Contains(names, field.Name) {
			names = append(names, field.Name)
		}
	}

	return names
}

func (n Node) EveryConstructorParamOptional() bool {
	class := php.EnclosingClass(n.Match)
	var promoted []engine.Match
	for _, param := range php.ConstructorParams(class) {
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

	return !classHasAttribute(class, "TypeScript") || slices.ContainsFunc(promoted, func(param engine.Match) bool {
		return !hasOptionalDefault(param)
	})
}

func (n Node) HasOptionalTypeScriptDefaults() bool {
	return classHasAttribute(n.Match, "TypeScript") && !slices.ContainsFunc(php.PublicInstanceFields(n.Match), func(field php.Field) bool {
		return !hasOptionalDefault(field.Node)
	})
}

func hasOptionalDefault(field engine.Match) bool {
	return strings.TrimLeft((php.Node{Match: field.Child("default")}).NewClassName(), `\`) == Optional
}

func (n Node) HasOneOptionalValidatedField() bool {
	params := php.ConstructorParams(n.Match)
	if len(params) != 1 || !php.IsNullConstant(params[0].Child("default")) {
		return false
	}
	return slices.ContainsFunc(php.AttributeNames(params[0]), func(name string) bool {
		return strings.HasPrefix(strings.TrimLeft(name, `\`), `Spatie\LaravelData\Attributes\Validation\`)
	})
}

func (n Node) DeclaresDataFields() bool {
	if slices.ContainsFunc(php.Fields(n.Match), php.Field.IsPublicInstance) {
		return true
	}

	return slices.ContainsFunc(n.Traits(), func(trait engine.Match) bool {
		declaration, found := n.program().Declaration(trait.Name())
		if !found || n.Codebase().IsLibraryType(contract.PHP, trait.Name()) {
			return false
		}

		return len(php.PublicInstanceFields(declaration)) > 0
	})
}

func (n Node) HasOneRequiredDataField() bool {
	fields := php.PublicInstanceFields(n.Match)
	return len(fields) == 1 && !fields[0].Node.Child("default").Exists()
}

func (n Node) ModelsOmissionOnEveryDataField() bool {
	fields := php.PublicInstanceFields(n.Match)
	return len(fields) > 0 && !slices.ContainsFunc(fields, func(field php.Field) bool {
		return !typeIncludesOptional(field.Type)
	})
}

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

func (n Node) PageObjectMissingTypeScript() bool {
	class := php.EnclosingClass(n.Match)

	return class.Exists() && n.IsPageObject() && !classHasAttribute(class, "TypeScript")
}

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

func realClassesOf(members []*contract.Type) []string {
	var classes []string
	for _, member := range members {
		for _, class := range php.Written(member).Names() {
			class = strings.TrimLeft(class, `\`)
			if strings.ToLower(class) == "null" || class == Optional {
				continue
			}
			classes = append(classes, class)
		}
	}

	return classes
}

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
		if owner := php.ClassConstantOwner(firstArgument(attribute).Child("value")); owner != "" {
			return strings.TrimLeft(owner, `\`)
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
		classes := realClassesOf(written.Members)
		if len(classes) == 1 {
			return classes[0]
		}

		return ""
	case len(name.Names()) == 1 && written.Kind != "intersection":
		return strings.TrimLeft(name.Names()[0], `\`)
	}

	return ""
}

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

func IsDataCollectionName(written *contract.Type) bool {
	names := php.Written(written).Names()

	return written != nil && written.Kind != "union" && written.Kind != "intersection" && len(names) == 1 && strings.TrimLeft(names[0], `\`) == DataCollection
}

func (n Node) NullableWireObject() bool {
	class := php.EnclosingClass(n.Match)
	if !class.Exists() || !n.program().Extends(php.EnclosingClassName(n.Match), Data) || !classHasAttribute(class, "TypeScript") {
		return false
	}
	if n.isHookedProperty() || (n.Kind() != "Param" && n.Kind() != "Stmt_Property") {
		return false
	}
	inner := nullableClassName(n.Node().Declared)
	enum, _ := n.program().Declaration(inner)

	return inner != "" && (n.program().Extends(inner, Data) ||
		(n.program().IsEnum(inner) && !enum.Child("scalarType").Exists()))
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

func newClassName(node engine.Match) string {
	if class := node.Child("class"); node.Kind() == "Expr_New" && isName(class) {
		return class.Name()
	}

	return ""
}
