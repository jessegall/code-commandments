package engine

import "slices"

// Declaring is the type declaration that declares what a member access reads: the class its receiver holds,
// followed up its own line of parents until one declares a member of that name. It is the answer to "whose field
// is this?", which a name cannot give: two classes may both declare `state`, and `task.state` is one question
// where `plan.state` is another.
//
// It answers no node where the question has no honest answer: a node that reads no member, a receiver the scan
// gives no type, and a member nothing in the scan declares — a field of a class from outside the project among
// them, since what asks this is about the project's own data. A navigator never answers nil, so the chain reads
// on with every check of it false.
func (m Match) Declaring() Match {
	if m.file == nil {
		return Match{}
	}

	reads := m.reads()
	if !reads.Exists() {
		return Match{}
	}

	read := reads.Name()
	if read == "" {
		return Match{}
	}

	held := reads.Receiver().TypeName()
	if held == "" {
		return Match{}
	}

	for _, class := range m.line(held) {
		if declaration, declares := m.declaration(class, read); declares {
			return declaration
		}
	}

	return Match{}
}

// reads is the node whose member this question is about: the node itself where it reads one, and the left side of
// a comparison, which is what asks about a field — `task.state == TaskState.STARTED` is a question about Task, and
// the comparison reads no member of its own.
func (m Match) reads() Match {
	if m.Is(MemberAccess) {
		return m
	}

	if !m.Is(Comparison) {
		return Match{}
	}

	children := m.Children()
	if len(children) == 0 || !children[0].Is(MemberAccess) {
		return Match{}
	}

	return children[0]
}

// Declarer is the name of that type, as the scan names it: the whole dotted or namespaced name, so a glob over it
// can say which project the class belongs to. Empty where nothing declares it.
func (m Match) Declarer() string {
	declaring := m.Declaring()
	if !declaring.Exists() {
		return ""
	}

	return declaring.Identity()
}

// line is the class and every class it extends, nearest first, as the scan declares them.
func (m Match) line(class string) []string {
	for _, declaration := range m.file.Codebase().Declarations(class) {
		if declaration.Is(TypeDeclaration) {
			return append([]string{class}, declaration.Lineage()...)
		}
	}

	return []string{class}
}

// declaration is the declaration of the class that declares a member of that name itself.
func (m Match) declaration(class, member string) (Match, bool) {
	for _, declaration := range m.file.Codebase().Declarations(class) {
		if !declaration.Is(TypeDeclaration) {
			continue
		}
		// A field is often a declaration carrying no name of its own, with the name on what it declares: a PHP
		// property statement, a Python annotated assignment. DeclaredName answers both.
		if slices.ContainsFunc(declaration.Members(), func(declared Match) bool { return declared.DeclaredName() == member }) {
			return declaration, true
		}
	}

	return Match{}, false
}
