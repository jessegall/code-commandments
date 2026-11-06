package python

import (
	"regexp"
	"slices"
	"strings"
)

var (
	dictTypes     = []string{"dict", "Dict", "typing.Dict", "Mapping", "typing.Mapping", "MutableMapping", "typing.MutableMapping", "collections.abc.Mapping"}
	sequenceTypes = []string{"list", "List", "typing.List", "Sequence", "typing.Sequence", "collections.abc.Sequence", "Iterable", "typing.Iterable", "collections.abc.Iterable"}
	identifier    = regexp.MustCompile(`^[A-Za-z_]\w*$`)
)

// DictEntry is one entry of a dict display: its key and its value, no key for a `**` entry.
type DictEntry struct {
	Key   Node
	Value Node
}

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

func (n Node) stringKeys() []string {
	var keys []string
	for _, entry := range n.DictEntries() {
		if text, ok := entry.Key.Text(); ok {
			keys = append(keys, text)
		}
	}

	return keys
}

func (n Node) StringKeyCount() int {
	return len(n.stringKeys())
}

func (n Node) HasFieldNameKeys() bool {
	return !slices.ContainsFunc(n.DictEntries(), func(entry DictEntry) bool {
		text, ok := entry.Key.Text()

		return entry.Key.Exists() && (!ok || !identifier.MatchString(text))
	})
}

func (n Node) SpreadsAnother() bool {
	return slices.ContainsFunc(n.DictEntries(), func(entry DictEntry) bool { return !entry.Key.Exists() })
}

func (n Node) HasNestedCollectionValue() bool {
	return slices.ContainsFunc(n.DictEntries(), func(entry DictEntry) bool { return entry.Value.isDisplay() })
}

func (n Node) IsJSONSchema() bool {
	keys := n.stringKeys()

	return slices.Contains(keys, "type") && (slices.Contains(keys, "properties") || slices.Contains(keys, "items"))
}

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

func (n Node) IsProjection() bool {
	name := n.ProjectedName()

	return name != "" && slices.Contains(parameterNames(n.EnclosingFunction().Parameters()), name)
}

func (n Node) IsReturnedValue() bool {
	return n.Parent().ReturnedValue() == n
}

func (p *Program) IsInTypedDictFunction(n Node) bool {
	returns := n.EnclosingFunction().Child("returns")

	return returns.Exists() && p.IsTypedDict(returns)
}

func (p *Program) IsInContractMethod(n Node) bool {
	function := n.EnclosingFunction()

	return function.Exists() && (function.IsDunder() || p.IsOverride(function))
}

func (n Node) IsInSequenceFunction() bool {
	return n.EnclosingFunction().Child("returns").isSequenceType()
}

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

func (n Node) IsJSONDecode() bool {
	return n.IsCall() && (n.Callee().DottedName() == "json.loads" || n.Callee().DottedName() == "json.load")
}

func (n Node) DecodesItsOwnEncoding() bool {
	arguments := n.Arguments()

	return n.IsJSONDecode() && len(arguments) > 0 && arguments[0].IsCall() && arguments[0].Callee().DottedName() == "json.dumps"
}

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

func (n Node) IsWithinNamedConstructor() bool {
	return n.EnclosingFunction().IsNamedConstructor()
}

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

func (n Node) IsConstructorDeclaration() bool {
	return n.IsMethod() && n.Name() == "__init__"
}

func (n Node) Owner() string {
	if class := n.EnclosingClass(); class.Exists() {
		return n.File() + "::" + class.Name()
	}

	return n.File()
}

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

func (n Node) argumentValue() Node {
	if n.Kind() == "keyword" {
		return n.Child("value")
	}

	return n
}

func (n Node) isSoleReturnOfOwnDataclass() bool {
	method := n.EnclosingFunction()
	body, class := method.ChildrenIn("body"), method.Parent()

	return len(body) == 1 && body[0].ReturnedValue() == n && class.Kind() == "ClassDef" && class.IsDataclass() && n.constructs(class.Name())
}

func (n Node) constructs(class string) bool {
	callee := n.Callee()

	return n.IsCall() && ((callee.IsCall() && callee.Callee().DottedName() == "type") || callee.DottedName() == class || callee.DottedName() == "self.__class__")
}

func (n Node) IsValueWrittenAfterConstruction() bool {
	if n.Kind() != "ClassDef" || !n.IsDataclass() {
		return false
	}
	fields := n.InitFieldNames()

	return slices.ContainsFunc(n.MethodsAfterConstruction(), func(method Node) bool {
		return slices.ContainsFunc(method.ExpressionsIn(), func(expression Node) bool { return expression.setsOwnAttribute(fields) }) || method.assignsOwnField(fields)
	})
}

func (n Node) MethodsAfterConstruction() []Node {
	return slices.DeleteFunc(n.Methods(), func(method Node) bool { return method.Name() == "__init__" || method.Name() == "__post_init__" })
}

func (n Node) setsOwnAttribute(fields []string) bool {
	arguments := n.Arguments()
	if !n.IsCall() || n.Callee().DottedName() != "object.__setattr__" || len(arguments)+len(n.Keywords()) != 3 || len(arguments) < 2 {
		return false
	}
	field, _ := arguments[1].Text()

	return arguments[0].DottedName() == "self" && slices.Contains(fields, field)
}

func (n Node) assignsOwnField(fields []string) bool {
	return slices.ContainsFunc(n.statementsIn(), func(write Node) bool {
		return slices.ContainsFunc(write.writtenTargets(), func(target Node) bool {
			return target.IsOwnAttributeRead() && slices.Contains(fields, target.Name()) && !write.isMemoFill(target.DottedName())
		})
	})
}

func (n Node) isMemoFill(name string) bool {
	guard := n.Parent()
	test := guard.Child("test")

	return guard.Kind() == "If" && n.Node().Field == "body" && (test.testsNoneOf(name) || test.testsBlanknessOf(name) || test.testsEmptinessOf(name))
}

func (n Node) testsEmptinessOf(dotted string) bool {
	measured, zero, ok := n.comparedWith("==")
	arguments := measured.Arguments()

	return ok && measured.IsCall() && measured.Callee().DottedName() == "len" && len(arguments) == 1 && arguments[0].DottedName() == dotted &&
		zero.Node().Literal == "int" && strings.TrimSpace(zero.Written()) == "0"
}
