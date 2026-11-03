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
		return m.NameRead(), m.Receiver()
	case m.Is(Comparison):
		children := m.Children()
		if len(children) == 0 || !children[0].Is(MemberAccess) {
			return "", Match{}
		}

		return children[0].NameRead(), children[0].Receiver()
	case m.HasFlag("named"):
		return m.Name(), subjectOf(m.Parent())
	case m.Is(Call):
		callee := m.calledNode()
		if !callee.Is(MemberAccess) {
			return "", Match{}
		}

		return callee.NameRead(), callee.Receiver()
	}

	return "", Match{}
}

// calledNode is what a call calls, wherever its language keeps it: the member access of a method call, or the bare
// name of a free function.
func (m Match) calledNode() Match {
	for _, field := range calleeFields {
		if called := m.Child(field); called.Exists() {
			return called
		}
	}

	return Match{}
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

// Declared is the MEMBER this node names, as the scan declares it: the method a call reaches, the field an access
// reads, the field a named argument sets. Where `Declaring` answers whose member it is, this answers which member,
// so a rule may step onto the declaration itself and ask anything of it — the modifiers it carries, the file it
// sits in, the layer it belongs to.
//
// The bridge's own resolution answers it where the bridge wrote one, since a resolver that followed the call knows
// better than any walk here; otherwise the declaring type's own members answer it, by the name the node reads. No
// node where neither does, as every navigator here answers nothing rather than a guess.
func (m Match) Declared() Match {
	if m.file == nil {
		return Match{}
	}

	if resolved := m.resolvedTarget(); resolved.Exists() {
		return resolved
	}

	read, _ := m.names()
	declaring := m.Declaring()
	if read == "" || !declaring.Exists() {
		return Match{}
	}

	for _, member := range declaring.Members() {
		if member.DeclaredName() == read {
			return member
		}
	}

	return Match{}
}

// resolvedTarget is the declaration the bridge resolved this call or construction to; no node where it wrote none,
// which is every language whose bridge does not follow a call, and every call it could not follow.
func (m Match) resolvedTarget() Match {
	if m.node == nil || m.node.Target == nil || m.node.Target.Symbol == "" {
		return Match{}
	}

	for _, declaration := range m.file.Codebase().Declarations(m.node.Target.Symbol) {
		return declaration
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

// Readers are the nodes that reach this declaration by name: every member access, call and named argument of the
// codebase whose own Declared answers it. It is how a question about a MEMBER is answered where a symbol cannot
// answer it — a field read as `$this->held`, `self.held` or `this.held` refers to no symbol in most languages, so
// the declaration it reaches is resolved through the type its receiver holds, which is the same walk a rule's
// `declaration` target makes.
//
// The declaration's own name node is no reader, and neither is anything inside the declaration itself: a field is
// not read by the line that declares it.
func (m Match) Readers() []Match {
	if m.file == nil || !m.Exists() {
		return nil
	}

	var readers []Match
	for _, reader := range readersOf(m.file.Codebase())[m.Location()] {
		// What a plain assignment writes to is no reader of it: a field only ever written is a field nothing
		// reads, which is the whole question a rule about an unread member asks.
		if reader.node == m.node || m.isAbove(reader) || reader.IsOverwritten() {
			continue
		}

		readers = append(readers, reader)
	}

	return readers
}

// IsOverwritten says whether the node is what a plain assignment writes TO: the left side of an `=`, which
// stores a value and reads none. A compound assignment and an increment read the old value before writing it, so
// neither is this, and a language that writes no operator for its plain assignment says so by writing none.
func (m Match) IsOverwritten() bool {
	parent := m.Parent()
	if !parent.Is(Assignment) || !slices.Contains([]string{"=", ""}, parent.operator()) {
		return false
	}

	written := parent.Children()

	return len(written) > 0 && m.isAtOrWithin(written[0])
}

// isAtOrWithin says whether this node is that one or sits inside it, which is how the written side of an
// assignment holds a member access: `$this->lines[0] = x` writes through the access rather than reading it.
func (m Match) isAtOrWithin(other Match) bool {
	return m.node == other.node || other.isAbove(m)
}

// operator is the operator token the node carries; none for a node carrying no operator.
func (m Match) operator() string {
	if m.node == nil || m.node.Facts == nil {
		return ""
	}

	return m.node.Operator
}

// DeclaresAField says whether the parameter also declares a field of its type: PHP's promoted constructor
// parameter, which is written in a signature and read as `$this->held` everywhere else. The type's own members
// answer it, so a language says so by listing the parameter among them.
func (m Match) DeclaresAField() bool {
	if !m.Is(Parameter) {
		return false
	}

	for _, member := range m.EnclosingType().Members() {
		if member.node == m.node {
			return true
		}
	}

	return false
}

// IsUnreadMember says whether nothing in the codebase reads the member: no node reaches its declaration, the
// language itself does not reach it by name (a serialized property, a constructor), and it overrides nothing, so
// no caller reaches it through a supertype. It is false for anything that is no member, since a rule asking it of
// a statement would be asking nothing.
func (m Match) IsUnreadMember() bool {
	if m.Is(Function) || m.Is(TypeDeclaration) {
		return false
	}

	if m.Role() != "member" && !m.DeclaresAField() {
		return false
	}

	// Both ways a member is reached are asked: what resolves to its declaration through the type a receiver
	// holds, and what refers to its symbol where its language's resolver binds names to symbols. A member nothing
	// reaches either way is read by nothing.
	return !m.IsImplicit() && !m.IsOverride() && len(m.Readers()) == 0 && len(m.Referrers()) == 0
}

// readersOf indexes every node of the codebase by the declaration it reaches, each by that declaration's own
// location, read once: a question about one member otherwise walks the whole codebase, and a rule asks it of
// every member there is.
func readersOf(c *Codebase) map[string][]Match {
	return Analysis(c, "readers", func(c *Codebase) map[string][]Match {
		index := map[string][]Match{}
		for _, file := range c.files {
			for _, node := range file.Nodes() {
				reader := Match{node: node, file: file}
				if declared := reader.Declared(); declared.Exists() {
					index[declared.Location()] = append(index[declared.Location()], reader)
				}
			}
		}

		return index
	})
}
