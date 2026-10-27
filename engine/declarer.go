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

	read, holder := m.names()
	if read == "" {
		return Match{}
	}

	held := holder.TypeName()
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

// names is the member this question is about and the node holding the value that member belongs to.
//
// Three nodes name a member. A member access names its own, held by its receiver. A comparison names the one its
// left side reads, since `task.state == TaskState.STARTED` is a question about Task and the comparison reads no
// member of its own. And an argument passed BY NAME names the member it sets on a value the call was handed:
// `replace(task, state=…)` and `task.with_(state=…)` both name a field of the task.
//
// A CONSTRUCTION names nobody's field but its own type's, and building a value is not changing one: `Task(state=…)`
// from anywhere is how a Task is made, so it holds no value whose field is being set and answers nothing here. The
// value is what tells the two apart — a receiver, or an argument that is not itself passed by name — which is why
// a call carrying none is left alone.
func (m Match) names() (string, Match) {
	switch {
	case m.Is(MemberAccess):
		return m.Name(), m.Receiver()
	case m.Is(Comparison):
		children := m.Children()
		if len(children) == 0 || !children[0].Is(MemberAccess) {
			return "", Match{}
		}

		return children[0].Name(), children[0].Receiver()
	case m.HasFlag("named"):
		return m.Name(), subjectOf(m.Parent())
	}

	return "", Match{}
}

// subjectOf is what a call is about, for a field set by one of its named arguments.
func subjectOf(call Match) Match {
	if !call.Is(Call) && !call.Is(Construction) {
		return Match{}
	}

	if receiver := call.Receiver(); receiver.Exists() {
		return receiver
	}

	for _, argument := range call.Arguments() {
		if !argument.HasFlag("named") {
			return argument
		}
	}

	return Match{}
}

// DeclaredElsewhere says whether what the node names is declared in another file than this one. It is the question
// a rule about a type's own business asks: a field set from outside the file that declares it has gone around the
// methods its class wrote for changing itself. False where nothing declares it, so a rule says the owner too.
func (m Match) DeclaredElsewhere() bool {
	declaring := m.Declaring()

	return declaring.Exists() && declaring.File() != m.File()
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
