package spatie

import (
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/laravel"
)

// richAttributes are the attributes that make a Data class do more than map a payload onto its fields.
var richAttributes = []string{"WithCast", "WithCastable", "WithTransformer", "MapInputName", "MapName", "DataCollectionOf"}

// nameMappingAttributes are the attributes that rename a payload's keys on the way in.
var nameMappingAttributes = []string{"MapInputName", "MapName"}

// fromFactory is a magic `from<Type>` factory's name.
var fromFactory = regexp.MustCompile(`^from[A-Z]`)

// DataClassShape reads what a Data class's declaration makes of a payload: whether it remaps names, whether it does
// more than map one onto its fields, and whether it composes other Data.
type DataClassShape struct {
	program *php.Program
	classes map[string]engine.Match
}

var shapes = php.Memoised(readShapes)

// DataClassShapeOf is the codebase's Data class shapes.
func DataClassShapeOf(codebase *engine.Codebase) *DataClassShape {
	return shapes.Of(codebase)
}

func readShapes(codebase *engine.Codebase) *DataClassShape {
	shape := &DataClassShape{program: php.ProgramOf(codebase), classes: map[string]engine.Match{}}
	for _, file := range codebase.Of(contract.PHP).Files() {
		for _, node := range file.Nodes() {
			if node.Kind == "Stmt_Class" && node.Symbol != "" {
				shape.classes[node.Symbol] = file.Match(node.ID)
			}
		}
	}

	return shape
}

// ClassFor is the class the name declares.
func (s *DataClassShape) ClassFor(fqcn string) (engine.Match, bool) {
	class, ok := s.classes[strings.TrimLeft(fqcn, `\`)]

	return class, ok
}

// RemapsInputNames says whether the class, or a parent, renames a payload key on its way in.
func (s *DataClassShape) RemapsInputNames(fqcn string) bool {
	return s.remapsInputNames(fqcn, map[string]bool{})
}

func (s *DataClassShape) remapsInputNames(fqcn string, seen map[string]bool) bool {
	fqcn = strings.TrimLeft(fqcn, `\`)
	class, ok := s.classes[fqcn]
	if fqcn == "" || !ok || seen[fqcn] {
		return false
	}
	seen[fqcn] = true
	if php.HasAttribute(class, nameMappingAttributes...) {
		return true
	}
	for _, member := range class.Children() {
		if member.Kind() == "Stmt_Property" && php.HasAttribute(member, nameMappingAttributes...) {
			return true
		}
	}
	for _, param := range php.ConstructorParams(class) {
		if php.HasAttribute(param, nameMappingAttributes...) {
			return true
		}
	}
	parent := class.Child("extends")

	return parent.Exists() && s.remapsInputNames(parent.Name(), seen)
}

// IsRich says whether the class, or a parent, does more than map a payload onto its fields: a cast, a transformer,
// a renamed key, a typed collection, a `casts()` or a `from<Type>` factory, or a field that is itself cast.
func (s *DataClassShape) IsRich(fqcn string) bool {
	return s.isRich(fqcn, map[string]bool{})
}

func (s *DataClassShape) isRich(fqcn string, seen map[string]bool) bool {
	fqcn = strings.TrimLeft(fqcn, `\`)
	class, ok := s.classes[fqcn]
	if fqcn == "" || !ok || seen[fqcn] {
		return false
	}
	seen[fqcn] = true
	if php.HasAttribute(class, richAttributes...) {
		return true
	}
	for _, method := range php.Methods(class) {
		if method.Name() == "casts" || fromFactory.MatchString(method.Name()) {
			return true
		}
	}
	for _, param := range php.ConstructorParams(class) {
		if slices.Contains(param.Node().Flags, "promoted") && (php.HasAttribute(param, richAttributes...) || s.isCastableType(param.Node().Declared)) {
			return true
		}
	}
	parent := class.Child("extends")

	return parent.Exists() && s.isRich(parent.Name(), seen)
}

func (s *DataClassShape) isCastableType(written *contract.Type) bool {
	if written == nil {
		return false
	}
	if written.Kind == "union" || written.Kind == "intersection" {
		return slices.ContainsFunc(written.Members, s.isCastableType)
	}
	if written.Kind != "named" && written.Kind != "keyword" {
		return false
	}
	short := php.ShortName(written.Name)

	return strings.Contains(short, "DataCollection") || short == "Optional" || short == "Lazy" || s.program.Extends(written.Name, Data)
}

// ComposesMultipleData says whether the class holds two or more Data fields, directly or as a typed collection.
func (s *DataClassShape) ComposesMultipleData(fqcn string) bool {
	class, ok := s.program.Class(fqcn)
	if !ok {
		return false
	}
	count := 0
	for _, field := range php.Fields(class) {
		if s.typeNamesData(field.Type) || s.collectionElementIsData(field) {
			count++
		}
	}

	return count >= 2
}

func (s *DataClassShape) typeNamesData(written *contract.Type) bool {
	if written == nil {
		return false
	}
	if written.Kind == "union" || written.Kind == "intersection" {
		return slices.ContainsFunc(written.Members, s.typeNamesData)
	}

	return php.Written(written).Names() != nil && written.Kind != "keyword" && s.program.Extends(written.Name, Data)
}

func (s *DataClassShape) collectionElementIsData(field php.Field) bool {
	for _, attribute := range attributes(field.Declaration) {
		if php.ShortName(attribute.Child("name").Name()) != "DataCollectionOf" {
			continue
		}
		if element := attributeClassArgument(attribute); element != "" && s.program.Extends(element, Data) {
			return true
		}
	}

	return false
}

// attributeClassArgument is the class an attribute's first argument names: `X::class`, or a string.
func attributeClassArgument(attribute engine.Match) string {
	value := firstArgument(attribute).Child("value")
	switch value.Kind() {
	case "Expr_ClassConstFetch":
		if class := value.Child("class"); isName(class) {
			return class.Name()
		}
	case "Scalar_String":
		text, _ := value.Node().Value.Text()

		return text
	}

	return ""
}

// IsPageObject says whether the class is the composed Data a page returns: two or more Data fields, and a response is
// built from it.
func IsPageObject(codebase *engine.Codebase, fqcn string) bool {
	return DataClassShapeOf(codebase).ComposesMultipleData(fqcn) && laravel.ResponseSurfaceOf(codebase).IsResponseBound(fqcn)
}

// attributes is every attribute the declaration carries, in order.
func attributes(declaration engine.Match) []engine.Match {
	var all []engine.Match
	for _, group := range declaration.Children() {
		if group.Kind() != "AttributeGroup" {
			continue
		}
		for _, attribute := range group.Children() {
			if attribute.Kind() == "Attribute" {
				all = append(all, attribute)
			}
		}
	}

	return all
}

// firstArgument is the first argument a call or an attribute passes, by position in the source.
func firstArgument(node engine.Match) engine.Match {
	for _, argument := range node.Children() {
		if argument.Node().Field == "args" {
			return argument
		}
	}

	return engine.Match{}
}

func isName(node engine.Match) bool {
	return strings.HasPrefix(node.Kind(), "Name")
}
