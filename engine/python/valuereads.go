package python

import (
	"regexp"
	"slices"
	"strings"
)

var (
	// dictTypes is how an annotation spells a mapping.
	dictTypes = []string{"dict", "Dict", "typing.Dict", "Mapping", "typing.Mapping", "MutableMapping", "typing.MutableMapping", "collections.abc.Mapping"}
	// sequenceTypes is how an annotation spells a sequence of one kind of item.
	sequenceTypes = []string{"list", "List", "typing.List", "Sequence", "typing.Sequence", "collections.abc.Sequence", "Iterable", "typing.Iterable", "collections.abc.Iterable"}
	// identifier is a key that reads as a field name.
	identifier = regexp.MustCompile(`^[A-Za-z_]\w*$`)
)

// DictEntry is one entry of a dict display: its key and its value, no key for a `**` entry.
type DictEntry struct {
	Key   Node
	Value Node
}

// DictEntries is a dict display's entries, in source order.
func (n Node) DictEntries() []DictEntry {
	var entries []DictEntry
	var key Node
	for _, child := range n.Children() {
		if child.Node().Field == "keys" {
			key = child
			continue
		}
		entries = append(entries, DictEntry{Key: key, Value: child})
		key = Node{}
	}

	return entries
}

// stringKeys is the keys of a dict display that are string literals.
func (n Node) stringKeys() []string {
	var keys []string
	for _, entry := range n.DictEntries() {
		if text, ok := entry.Key.Text(); ok {
			keys = append(keys, text)
		}
	}

	return keys
}

// StringKeyCount is how many of a dict display's keys are string literals.
func (n Node) StringKeyCount() int {
	return len(n.stringKeys())
}

// HasFieldNameKeys says whether every key a dict display writes is a string that reads as a field name.
func (n Node) HasFieldNameKeys() bool {
	return !slices.ContainsFunc(n.DictEntries(), func(entry DictEntry) bool {
		text, ok := entry.Key.Text()

		return entry.Key.Exists() && (!ok || !identifier.MatchString(text))
	})
}

// SpreadsAnother says whether a dict display spreads another mapping into itself.
func (n Node) SpreadsAnother() bool {
	return slices.ContainsFunc(n.DictEntries(), func(entry DictEntry) bool { return !entry.Key.Exists() })
}

// HasNestedCollectionValue says whether a value of a dict display is itself a collection display.
func (n Node) HasNestedCollectionValue() bool {
	return slices.ContainsFunc(n.DictEntries(), func(entry DictEntry) bool { return entry.Value.isDisplay() })
}

// IsJSONSchema says whether a dict display is a JSON schema: a `type` beside `properties` or `items`.
func (n Node) IsJSONSchema() bool {
	keys := n.stringKeys()

	return slices.Contains(keys, "type") && (slices.Contains(keys, "properties") || slices.Contains(keys, "items"))
}

// IsMemberTable says whether every value of a dict display is a member of one and the same name: a table over an
// enum or a class's constants.
func (n Node) IsMemberTable() bool {
	var owners []string
	for _, entry := range n.DictEntries() {
		owner := entry.Value.Child("value")
		if entry.Value.Kind() != "Attribute" || owner.Kind() != "Name" {
			return false
		}
		if !slices.Contains(owners, owner.DottedName()) {
			owners = append(owners, owner.DottedName())
		}
	}

	return len(owners) == 1
}

// ProjectedName is the one name every value of a dict display reads data from; empty when there is not exactly one.
func (n Node) ProjectedName() string {
	var names []string
	for _, entry := range n.DictEntries() {
		for _, name := range entry.Value.DataNames() {
			if !slices.Contains(names, name) {
				names = append(names, name)
			}
		}
	}
	if len(names) != 1 {
		return ""
	}

	return names[0]
}

// IsProjection says whether a dict display projects one of its def's parameters: every value read off it.
func (n Node) IsProjection() bool {
	name := n.ProjectedName()

	return name != "" && slices.Contains(parameterNames(n.EnclosingFunction().Parameters()), name)
}

// IsReturnedValue says whether the expression is what a return statement returns.
func (n Node) IsReturnedValue() bool {
	return n.Parent().ReturnedValue() == n
}

// IsInTypedDictFunction says whether the expression sits in a def that declares it returns a TypedDict.
func (p *Program) IsInTypedDictFunction(n Node) bool {
	returns := n.EnclosingFunction().Child("returns")

	return returns.Exists() && p.IsTypedDict(returns.DottedName())
}

// IsInContractMethod says whether the expression sits in a def whose shape a contract sets: a dunder, or an
// override of a base's method.
func (p *Program) IsInContractMethod(n Node) bool {
	function := n.EnclosingFunction()

	return function.Exists() && (function.IsDunder() || p.IsOverride(function))
}

// IsInSequenceFunction says whether the expression sits in a def that declares it returns a sequence.
func (n Node) IsInSequenceFunction() bool {
	return n.EnclosingFunction().Child("returns").isSequenceType()
}

// isSequenceType says whether an annotation is a sequence of one kind of item: a list, a sequence, an iterable, or
// a tuple of any length.
func (n Node) isSequenceType() bool {
	named := n
	if n.Kind() == "Subscript" {
		named = n.Child("value")
	}
	if slices.Contains([]string{"tuple", "Tuple", "typing.Tuple"}, named.DottedName()) {
		index := n.Child("slice")
		elements := index.ChildrenIn("elts")

		return n.Kind() == "Subscript" && index.Kind() == "Tuple" && len(elements) > 0 && elements[len(elements)-1].Node().Literal == "ellipsis"
	}

	return slices.Contains(sequenceTypes, named.DottedName())
}

// IsPositionalTuple says whether the expression is a tuple of three or more values read from two or more places:
// a record whose fields are known only by position.
func (n Node) IsPositionalTuple() bool {
	elements := n.ChildrenIn("elts")
	if n.Kind() != "Tuple" || len(elements) < 3 || slices.ContainsFunc(elements, isStarred) {
		return false
	}
	var roots []string
	for _, element := range elements {
		if root := element.RootName(); root != "" && !slices.Contains(roots, root) {
			roots = append(roots, root)
		}
	}

	return len(roots) >= 2
}

// IsJSONDecode says whether the expression decodes JSON.
func (n Node) IsJSONDecode() bool {
	return n.IsCall() && (n.Callee().DottedName() == "json.loads" || n.Callee().DottedName() == "json.load")
}

// DecodesItsOwnEncoding says whether a JSON decode reads back what `json.dumps` just wrote: a deep copy.
func (n Node) DecodesItsOwnEncoding() bool {
	arguments := n.Arguments()

	return n.IsJSONDecode() && len(arguments) > 0 && arguments[0].IsCall() && arguments[0].Callee().DottedName() == "json.dumps"
}

// StringKeyBase is what an expression reads by a string key: `row` in `row["sku"]` and `row.get("sku")`.
func (n Node) StringKeyBase() (Node, bool) {
	if _, isText := n.Child("slice").Text(); n.Kind() == "Subscript" && isText {
		return n.Child("value"), true
	}
	callee, arguments := n.Callee(), n.Arguments()
	if n.IsCall() && callee.Kind() == "Attribute" && callee.Name() == "get" && len(arguments) > 0 {
		if _, isText := arguments[0].Text(); isText {
			return callee.Child("value"), true
		}
	}

	return Node{}, false
}

// IsDictKeyRead says whether the expression reads, by a string key, a name its def annotates as a mapping.
func (n Node) IsDictKeyRead() bool {
	base, ok := n.StringKeyBase()
	if !ok || base.Kind() != "Name" {
		return false
	}
	annotation, annotated := n.EnclosingFunction().AnnotationOf(base.Name())
	named := annotation
	if annotation.Kind() == "Subscript" {
		named = annotation.Child("value")
	}

	return annotated && slices.Contains(dictTypes, named.DottedName())
}

// IsWithinNamedConstructor says whether the expression sits in a named constructor, where loose data becomes the
// class.
func (n Node) IsWithinNamedConstructor() bool {
	return n.EnclosingFunction().IsNamedConstructor()
}

// ValueParamSignature is the def's scalar parameters as sorted `type name` pairs, when there are three or more of
// them; empty otherwise. Two defs with one signature take the same loose values.
func (n Node) ValueParamSignature() []string {
	var fields []string
	for _, parameter := range n.Parameters() {
		if spelled := parameter.Child("annotation").DottedName(); slices.Contains(scalars, spelled) {
			fields = append(fields, spelled+" "+parameter.Name())
		}
	}
	slices.Sort(fields)
	if len(fields) < 3 {
		return nil
	}

	return fields
}

// IsConstructorDeclaration says whether the def is a class's __init__.
func (n Node) IsConstructorDeclaration() bool {
	return n.IsMethod() && n.Name() == "__init__"
}

// Owner is who a node belongs to: its file and nearest class, or its file alone.
func (n Node) Owner() string {
	if class := n.EnclosingClass(); class.Exists() {
		return n.File() + "::" + class.Name()
	}

	return n.File()
}

// IsHandRolledReplace says whether a call rebuilds its method's own dataclass carrying three or more of its fields
// across unchanged and changing the rest: `dataclasses.replace` written by hand.
func (n Node) IsHandRolledReplace() bool {
	arguments := n.Arguments()
	for _, keyword := range n.Keywords() {
		arguments = append(arguments, keyword)
	}
	if !n.IsCall() || len(arguments) == 0 || slices.ContainsFunc(arguments, isStarred) || slices.ContainsFunc(n.Keywords(), func(keyword Node) bool { return keyword.Name() == "" }) {
		return false
	}
	carried := 0
	for _, argument := range arguments {
		if argument.argumentValue().IsOwnAttributeRead() {
			carried++
		}
	}

	return carried >= 3 && carried < len(arguments) && n.isSoleReturnOfOwnDataclass()
}

// argumentValue is what an argument hands over: a keyword's value, else the argument itself.
func (n Node) argumentValue() Node {
	if n.Kind() == "keyword" {
		return n.Child("value")
	}

	return n
}

// isSoleReturnOfOwnDataclass says whether the call is the one statement of its method, returned, and builds the
// dataclass the method belongs to.
func (n Node) isSoleReturnOfOwnDataclass() bool {
	method := n.EnclosingFunction()
	body, class := method.ChildrenIn("body"), method.Parent()

	return len(body) == 1 && body[0].ReturnedValue() == n && class.Kind() == "ClassDef" && class.IsDataclass() && n.constructs(class.Name())
}

// constructs says whether the call builds the named class: by its name, through `self.__class__`, or `type(self)`.
func (n Node) constructs(class string) bool {
	callee := n.Callee()

	return n.IsCall() && ((callee.IsCall() && callee.Callee().DottedName() == "type") || callee.DottedName() == class || callee.DottedName() == "self.__class__")
}

// IsValueWrittenAfterConstruction says whether a dataclass writes one of its own fields after it is built: a value
// that changes under its holders.
func (n Node) IsValueWrittenAfterConstruction() bool {
	if n.Kind() != "ClassDef" || !n.IsDataclass() {
		return false
	}
	fields := n.InitFieldNames()

	return slices.ContainsFunc(n.MethodsAfterConstruction(), func(method Node) bool {
		return slices.ContainsFunc(method.ExpressionsIn(), func(expression Node) bool { return expression.setsOwnAttribute(fields) }) || method.assignsOwnField(fields)
	})
}

// MethodsAfterConstruction is every method of the class but __init__ and __post_init__.
func (n Node) MethodsAfterConstruction() []Node {
	return slices.DeleteFunc(n.Methods(), func(method Node) bool { return method.Name() == "__init__" || method.Name() == "__post_init__" })
}

// setsOwnAttribute says whether the expression is `object.__setattr__(self, "<field>", …)` on one of the fields.
func (n Node) setsOwnAttribute(fields []string) bool {
	arguments := n.Arguments()
	if !n.IsCall() || n.Callee().DottedName() != "object.__setattr__" || len(arguments)+len(n.Keywords()) != 3 || len(arguments) < 2 {
		return false
	}
	field, _ := arguments[1].Text()

	return arguments[0].DottedName() == "self" && slices.Contains(fields, field)
}

// assignsOwnField says whether the method assigns one of the fields on self, a memo filled on first use aside.
func (n Node) assignsOwnField(fields []string) bool {
	return slices.ContainsFunc(n.statementsIn(), func(write Node) bool {
		return slices.ContainsFunc(write.writtenTargets(), func(target Node) bool {
			return target.IsOwnAttributeRead() && slices.Contains(fields, target.Name()) && !write.isMemoFill(target.DottedName())
		})
	})
}

// isMemoFill says whether the write sits in the body of an if that asks whether the name is still unset: None,
// blank or empty.
func (n Node) isMemoFill(name string) bool {
	guard := n.Parent()
	test := guard.Child("test")

	return guard.Kind() == "If" && n.Node().Field == "body" && (test.testsNoneOf(name) || test.testsBlanknessOf(name) || test.testsEmptinessOf(name))
}

// testsEmptinessOf says whether the expression asks `len(<name>) == 0`.
func (n Node) testsEmptinessOf(dotted string) bool {
	measured, zero, ok := n.comparedWith("==")
	arguments := measured.Arguments()

	return ok && measured.IsCall() && measured.Callee().DottedName() == "len" && len(arguments) == 1 && arguments[0].DottedName() == dotted &&
		zero.Node().Literal == "int" && strings.TrimSpace(zero.Written()) == "0"
}
