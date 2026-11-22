package engine

import (
	"crypto/sha1"
	"encoding/hex"
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// Fingerprint is a subtree's structure, hashed: each node's kind, the role it fills, its name, operator, form and
// modifiers and a literal's value, in source order, with no-op statements dropped. Normalised, a local name and a
// literal's value read as their kind alone, so two copies that differ only in them hash the same; a callee keeps its
// name either way, since calling another function is another behaviour.
func (m Match) Fingerprint(normalize bool) string {
	mode := "exact"
	if normalize {
		mode = "normalized"
	}
	written, _ := m.FingerprintAs(Fingerprinting{Mode: mode})

	return written
}

// Fingerprinting says what a fingerprint keeps of a subtree. Mode exact keeps every name and value; normalized reads
// a local name and a literal as their kind; renamed numbers each local binding by its first appearance, so a copy
// that renames its locals one for one hashes the same and one that uses a variable twice does not; shape keeps only
// kinds and roles; calls is the order of the resolved targets called; signature is a function's name, parameter
// types and return type; text is the source with its white space collapsed. LiteralKinds reads each literal as its
// kind, Commutative sorts the operands of an operator whose order does not matter.
type Fingerprinting struct {
	Mode         string
	LiteralKinds bool
	Commutative  bool
}

// FingerprintModes are the modes a fingerprint is taken in.
var FingerprintModes = []string{"exact", "normalized", "renamed", "shape", "calls", "signature", "text"}

// FingerprintAs is the subtree's fingerprint taken as asked, and whether it can be: not where a node the parser
// recovered from or could not translate lies inside, nor for calls whose target, or a signature whose type, the
// compiler did not resolve.
func (m Match) FingerprintAs(how Fingerprinting) (string, bool) {
	if m.node == nil {
		return "", false
	}
	var written string
	switch how.Mode {
	case "calls":
		calls, resolved := m.calledTargets()
		if !resolved {
			return "", false
		}
		written = strings.Join(calls, ";")
	case "signature":
		signature, resolved := m.signature()
		if !resolved {
			return "", false
		}
		written = signature
	case "text":
		text, read := m.Text()
		if !read {
			return "", false
		}
		written = strings.Join(strings.Fields(text), " ")
	default:
		if !slices.Contains(FingerprintModes, how.Mode) {
			return "", false
		}
		walked := fingerprintWalk{how: how, numbered: map[int]int{}}
		written = walked.structure(m)
		if walked.recovered {
			return "", false
		}
	}
	sum := sha1.Sum([]byte(written))

	return hex.EncodeToString(sum[:]), true
}

// SyntaxWeight is how many nodes a subtree holds, no-op statements left out.
func (m Match) SyntaxWeight() int {
	if m.node == nil || m.node.Kind == "no-op" {
		return 0
	}
	weight := 1
	for _, child := range m.Children() {
		weight += child.SyntaxWeight()
	}

	return weight
}

type fingerprintWalk struct {
	how       Fingerprinting
	numbered  map[int]int
	recovered bool
	entered   bool
}

// role is the node's role in its parent, left out for the subtree's own root: where a subtree sits is not what it is.
func (w *fingerprintWalk) role(node *contract.Node) string {
	if !w.entered {
		w.entered = true

		return ""
	}

	return node.InRole()
}

// orderFree are the operators whose operands may be swapped without changing what they mean.
var orderFree = []string{"add", "multiply", "bit-and", "bit-or", "bit-xor", "equal", "not-equal", "identical", "not-identical", "and", "or"}

func (w *fingerprintWalk) structure(m Match) string {
	var out strings.Builder
	w.write(&out, m)

	return out.String()
}

func (w *fingerprintWalk) write(out *strings.Builder, m Match) {
	node := m.node
	if node.Incomplete || node.Kind == "unsupported" {
		w.recovered = true
	}
	normalize := w.how.Mode == "normalized"
	role := w.role(node)
	switch {
	case w.how.Mode == "shape":
		out.WriteString("(" + node.Kind + "|" + role)
		for _, child := range m.statedChildren() {
			out.WriteByte('|')
			w.write(out, child)
		}
		out.WriteByte(')')

		return
	case node.Kind == "literal" && (normalize || w.how.LiteralKinds):
		out.WriteString("(literal|" + node.LiteralKind + ")")

		return
	case node.Kind == "literal" && node.Value != nil:
		written, _ := node.Value.MarshalJSON()
		out.WriteString("(literal|" + node.LiteralKind + "|" + string(written) + ")")

		return
	case node.Kind == "reference" && normalize && role != "callee":
		out.WriteString("(reference)")

		return
	case w.how.Mode == "renamed" && node.Kind == "binding":
		out.WriteString("(binding|" + role + "|v" + w.number(node.ID) + ")")

		return
	case w.how.Mode == "renamed" && node.Kind == "reference" && len(node.Linked("binding")) == 1:
		out.WriteString("(reference|" + role + "|v" + w.number(node.Linked("binding")[0]) + ")")

		return
	}
	out.WriteString("(" + node.Kind + "|" + role)
	if !normalize || !node.Is("declaration") {
		out.WriteString("|" + node.Name)
	}
	out.WriteString("|" + node.Operator + "|" + node.Form + "|" + strings.Join(node.Modifiers, " "))
	if w.how.Commutative && slices.Contains(orderFree, node.Operator) {
		var children []string
		for _, child := range m.statedChildren() {
			children = append(children, w.structure(child))
		}
		slices.Sort(children)
		for _, child := range children {
			out.WriteString("|" + child)
		}
	} else {
		for _, child := range m.statedChildren() {
			out.WriteByte('|')
			w.write(out, child)
		}
	}
	out.WriteByte(')')
}

// number is a local binding's place among the bindings the subtree names, counted in the order each first appears.
func (w *fingerprintWalk) number(binding int) string {
	if _, seen := w.numbered[binding]; !seen {
		w.numbered[binding] = len(w.numbered)
	}

	return strconv.Itoa(w.numbered[binding])
}

// calledTargets is each call and construction under the node, in source order, named by the declaration it resolves to.
func (m Match) calledTargets() ([]string, bool) {
	var targets []string
	resolved := true
	var walk func(at Match)
	walk = func(at Match) {
		if kind := at.node.Kind; kind == "call" || kind == "construction" {
			if at.node.Target == nil {
				resolved = false
			} else {
				targets = append(targets, at.node.Target.Type+"::"+at.node.Target.Name)
			}
		}
		for _, child := range at.Children() {
			walk(child)
		}
	}
	walk(m)

	return targets, resolved
}

// signature is a function's name, its parameters' types in order and its return type, each as the compiler resolved it.
func (m Match) signature() (string, bool) {
	if m.node.Kind != "function" {
		return "", false
	}
	typeOf := func(written Match) (string, bool) {
		if !written.Exists() {
			return "", true
		}

		return signatureType(written)
	}
	var parameters []string
	for _, parameter := range m.ChildrenIn("parameters") {
		declared, resolved := typeOf(parameter.Child("declaredType"))
		if !resolved {
			return "", false
		}
		parameters = append(parameters, declared)
	}
	returned, resolved := typeOf(m.Child("returnType"))

	return m.node.Name + "(" + strings.Join(parameters, ",") + "):" + returned, resolved
}

// signatureType is a written type as a signature compares it: a tuple by its elements' types, labels left out, a
// keyword by its name, a named type by the declaration it refers to, a compound type by its form and its parts, and
// anything else by the type the compiler resolved; not read where none of these is known.
func signatureType(written Match) (string, bool) {
	node := written.Node()
	parts := func(role, open, separator, close string) (string, bool) {
		var read []string
		for _, part := range written.ChildrenIn(role) {
			if part.Node().Kind == "tuple-element" {
				part = part.Child("type")
			}
			text, known := signatureType(part)
			if !known {
				return "", false
			}
			read = append(read, text)
		}

		return open + strings.Join(read, separator) + close, true
	}
	switch {
	case node.Kind == "tuple-type":
		return parts("elements", "(", ",", ")")
	case node.Form == "keyword" && node.Name != "":
		return node.Name, true
	case node.Form == "named" && node.Refers != "" && len(written.ChildrenIn("typeArguments")) == 0:
		return node.Refers, true
	case node.Form == "nullable" || node.Form == "union":
		members, known := unionMembers(written)
		if !known {
			return "", false
		}
		slices.Sort(members)

		return "union(" + strings.Join(slices.Compact(members), "|") + ")", true
	case node.Form == "intersection":
		return parts("members", node.Form+"(", "|", ")")
	}
	if children := written.Children(); node.Kind == "type-expression" && node.Form != "named" && len(children) > 0 {
		var read []string
		for _, child := range children {
			text, known := signatureType(child)
			if !known {
				return "", false
			}
			read = append(read, child.Node().InRole()+":"+text)
		}

		return node.Form + "(" + strings.Join(read, ",") + ")", true
	}
	if resolved := written.Typed(); resolved != nil {
		return resolved.Text, true
	}

	return "", false
}

// unionMembers are the types a union or a nullable type admits, nested unions flattened, null spelled as the
// keyword it is: ?T and T|null admit the same.
func unionMembers(written Match) ([]string, bool) {
	node := written.Node()
	switch node.Form {
	case "nullable":
		element, known := unionMembers(written.Child("element"))
		return append(element, "null"), known
	case "union":
		var members []string
		for _, member := range written.ChildrenIn("members") {
			read, known := unionMembers(member)
			if !known {
				return nil, false
			}
			members = append(members, read...)
		}

		return members, true
	}
	if node.Form == "keyword" && strings.EqualFold(node.Name, "null") {
		return []string{"null"}, true
	}
	text, known := signatureType(written)

	return []string{text}, known
}

// Reports says whether a finding in the file is reported: every file of the codebase, or the files a run narrowed it to.
func (c *Codebase) Reports(file *File) bool {
	return !file.dependency && (len(c.reporting) == 0 || c.reporting[file.Path])
}

// ResolvedImport is one import that reaches a file of the project: the module it is written in and the module it reaches.
type ResolvedImport struct {
	At   Match
	From string
	To   string
}

// ResolvedImports is every import of the project's own files that reaches another of its modules, in file and source
// order, read once per codebase. An import reaches the file its resolves fact names, else the module its source
// names, else the module of each project declaration its bindings, or it, refer to.
func ResolvedImports(codebase *Codebase) []ResolvedImport {
	return Analysis(codebase, "resolved-imports", func(codebase *Codebase) []ResolvedImport {
		byPath := map[string]string{}
		modules := map[string]bool{}
		for _, file := range codebase.Files() {
			if !file.dependency && file.Module != "" {
				byPath[file.Path] = file.Module
				modules[file.Module] = true
			}
		}
		var resolved []ResolvedImport
		for _, file := range codebase.Files() {
			if file.dependency || file.Module == "" {
				continue
			}
			for _, node := range file.OfKind("import") {
				at := Match{node: node, file: file}
				for _, to := range codebase.reached(at, byPath, modules) {
					resolved = append(resolved, ResolvedImport{At: at, From: file.Module, To: to})
				}
			}
		}

		return resolved
	})
}

func (c *Codebase) reached(imported Match, byPath map[string]string, modules map[string]bool) []string {
	node := imported.node
	if to, reaches := byPath[node.Resolves]; reaches && node.Resolves != "" {
		return []string{to}
	}
	if modules[node.Source] {
		return []string{node.Source}
	}
	symbols := []string{node.Refers}
	bindings, _ := node.Role("bindings")
	for _, binding := range bindings {
		symbols = append(symbols, binding.Refers)
	}
	var reached []string
	for _, symbol := range symbols {
		if symbol == "" {
			continue
		}
		for _, declaration := range c.Declarations(symbol) {
			if module := byPath[declaration.File()]; module != "" && !slices.Contains(reached, module) {
				reached = append(reached, module)
			}
		}
	}

	return reached
}

func (m Match) statedChildren() []Match {
	return slices.DeleteFunc(m.Children(), func(child Match) bool { return child.node.Kind == "no-op" })
}
