package engine

// A condition is a concept in disguise once it has this many parts asking something of this many different
// objects. Three parts over two objects is where a reader stops being able to say what the whole MEANS and
// starts having to work it out from the mechanics.
const (
	conditionParts    = 3
	conditionSubjects = 2
)

// TestsAnUnnamedConcept says whether the node is a logical chain standing for a concept nobody named: three or
// more parts asking something of two or more different objects, where a branch or a loop decides. The fix is a
// property or a method that answers the question in a name, so the body reads as what it means.
//
// It counts the OBJECTS and never the parts alone, which is the crude rule a count would be: `$at >= $from &&
// $at <= $until && $at !== $closed` has three parts over one subject and is a range check, not a concept. A
// chain a function RETURNS is already the name that was asked for, and a guard clause that leaves the body says
// what it means by leaving, so neither is a concept in hiding.
func TestsAnUnnamedConcept(m Match) bool {
	if !m.Is(Conjunction) {
		return false
	}
	if m.Parent().Is(Conjunction) {
		return false
	}
	if !m.EnclosingFunction().Exists() {
		return false
	}
	if !decidesABranch(m) {
		return false
	}
	if guardsFromTheTop(m.Parent()) {
		return false
	}
	parts := conditionChainOf(m)
	if len(parts) < conditionParts {
		return false
	}
	if declaresAName(parts) {
		return false
	}

	return len(subjectsOf(parts)) >= conditionSubjects
}

// decidesABranch says whether the chain is what a branch or a loop decides by, which is where a condition
// stands for something; a chain anywhere else is a value, and a value is named by what holds it.
func decidesABranch(m Match) bool {
	parent := m.Parent()

	return parent.Is(Branch) || parent.Is(Loop)
}

// guardsFromTheTop says whether the branch is a guard clause standing at the top of its body: it does nothing
// but leave, and nothing before it does anything either — another guard, an import, a docstring, or a line
// binding a name to a value.
// Such a condition says what it means by leaving, and the flow discipline asks for it by name. One that leaves
// from the MIDDLE of a body, after the work has started, is not that: it is an answer worked out among the work,
// and naming it is the same gain as naming any other.
func guardsFromTheTop(branch Match) bool {
	if !onlyLeaves(branch) {
		return false
	}
	// The statements it stands among are the ones filed under its own field, which is how a language that hangs a
	// body straight off its construct is read beside one that wraps it in a block.
	for _, statement := range branch.Parent().ChildrenIn(branch.Node().Field) {
		if statement.Node() == branch.Node() {
			return true
		}
		if statement.Binds() || statement.Is(Import) || says(statement) {
			continue
		}
		if !statement.Is(Branch) || !onlyLeaves(statement) {
			return false
		}
	}

	return false
}

// says whether the statement is a line of words and nothing else: a docstring, which stands above the guards of
// the body it opens and does nothing.
func says(statement Match) bool {
	if !statement.Is(ExpressionStatement) && !statement.Is(Literal) {
		return false
	}
	for _, held := range append(statement.Children(), statement) {
		if held.Is(Literal) {
			return true
		}
	}

	return false
}

// OnlyLeaves says whether the branch does nothing but leave the body it stands in: a guard clause, which is the
// shape the flow discipline asks for rather than one it objects to. Go's `if err != nil { return err }` is the
// one every function writes, and `if !ok { continue }` is how a loop says what it skips. A reader holds neither
// open, because there is nothing inside them to come back to, so a rule counting what the code buries must not
// count these.
func (m Match) OnlyLeaves() bool {
	return onlyLeaves(m)
}

// onlyLeaves says whether the branch does nothing but leave the body it stands in.
func onlyLeaves(branch Match) bool {
	for _, child := range branch.Children() {
		if child.Is(BailOut) {
			return true
		}
		if !child.Is(Block) {
			continue
		}
		body := child.Children()
		if len(body) == 1 && body[0].Is(BailOut) {
			return true
		}
	}

	return false
}

// conditionChainOf are the parts of a logical chain, however the language nests them: `a && b && c` is one
// chain of three and never a chain of two holding a chain of two.
func conditionChainOf(m Match) []Match {
	var parts []Match
	for _, child := range m.Children() {
		if child.Is(Conjunction) {
			parts = append(parts, conditionChainOf(child)...)

			continue
		}
		parts = append(parts, child)
	}

	return parts
}

// declaresAName says whether a part of the chain declares a name the body then uses: `TryGetValue(name, out var
// artists) && artists.Length > 0`, or `item is UserView view && view.IsEmpty()`. Such a condition cannot be moved
// behind a name of its own, since the name it binds would go with it, so there is no fix to point at.
func declaresAName(parts []Match) bool {
	for _, part := range parts {
		for _, held := range append(part.Descendants(), part) {
			if held.Declares() {
				return true
			}
		}
	}

	return false
}

// subjectsOf are the objects a condition asks something of: the root of everything a part is sent to. A part
// comparing locals is sent to nobody, which is why a range check over one variable is no concept in hiding, and
// a name that refers to a symbol of its own — a class, an enum, a constant — is no object either, since reading
// a constant off a class asks the class nothing.
func subjectsOf(parts []Match) []string {
	var subjects []string
	for _, part := range parts {
		for _, sent := range append(part.Descendants(), part) {
			if !sent.Is(Call) && !sent.Is(MemberAccess) {
				continue
			}
			root := rootOf(sent.Receiver())
			if !root.Exists() || root.Refers() != "" {
				continue
			}
			if subject := root.Written(); subject != "" && !contains(subjects, subject) {
				subjects = append(subjects, subject)
			}
		}
	}

	return subjects
}

// rootOf is the name a receiver starts from: `$order` for `$order->lines[0]`, `self` for `self.period`. A
// receiver is written before what it is asked for, in every language, so the first name it holds is its root.
func rootOf(receiver Match) Match {
	if receiver.Is(Identifier) || receiver.Is(SelfReference) {
		return receiver
	}
	for _, held := range receiver.Descendants() {
		if held.Is(Identifier) || held.Is(SelfReference) {
			return held
		}
	}

	return Match{}
}

// contains says whether the subject is already counted.
func contains(subjects []string, subject string) bool {
	for _, counted := range subjects {
		if counted == subject {
			return true
		}
	}

	return false
}
