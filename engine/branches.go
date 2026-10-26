package engine


// HasRedundantElse says whether the branch carries an `else` though its taken path already left: the else's
// statements can stand after it, at the enclosing level, because the exit above them already said the rest runs
// only when the condition did not hold. A branch whose else is the next rung is a ladder, not a guard with an
// else, so it is not this.
//
// The reading is one for every language: the neutral kinds say what a branch and an exit are, and the language's
// grammar says where its tree files the taken path and the else.
func HasRedundantElse(branch Match) bool {
	if !branch.Is(Branch) || branch.IsContinuation() {
		return false
	}
	otherwise := branch.Otherwise()
	if len(otherwise) == 0 || (len(otherwise) == 1 && otherwise[0].IsContinuation()) {
		return false
	}
	body := branch.Body()

	return len(body) > 0 && body[len(body)-1].Is(BailOut)
}

// IsSoleLoopBodyGuard says whether the branch is the whole body of a loop, burying two statements or more a level
// deep behind its condition where `continue` past what does not apply would keep them flat. A body that ends by
// leaving the loop is picking the one item it wanted, not guarding its work, and a branch with an else decides
// between two paths rather than guarding one.
//
// A branch that BINDS a name for its own body is not this either: Go's `if v, ok := m[k]; ok`, C#'s `out var`
// and `is T v`, Python's `:=`. Inverting one into a guard takes the binding out of the branch and into the loop,
// widening a name that was scoped to the only code entitled to read it, so the fix the rule would ask for is
// worse than what it flagged. The condition reader pardons the same shape for the same reason.
func IsSoleLoopBodyGuard(branch Match) bool {
	if !branch.Is(Branch) || !branch.Condition().Exists() || len(branch.Otherwise()) > 0 {
		return false
	}
	if branch.BindsItsOwnTest() {
		return false
	}
	work := branch.Body()
	if len(work) < 2 || work[len(work)-1].Is(BailOut) {
		return false
	}
	loop := branch.Parent()
	if loop.Is(Block) {
		loop = loop.Parent()
	}
	if !loop.Is(Loop) {
		return false
	}
	body := loop.Body()

	return len(body) == 1 && body[0].Node() == branch.Node()
}

// LadderRungs are the branches one choice is written as: the branch itself, then each one continuing it. A branch
// that continues another opens no ladder of its own, so it answers with itself alone and the rungs are counted
// once, from the top.
func LadderRungs(branch Match) []Match {
	rungs := []Match{branch}
	for held := branch; ; {
		otherwise := held.Otherwise()
		if len(otherwise) != 1 || !otherwise[0].IsContinuation() {
			return rungs
		}
		held = otherwise[0]
		rungs = append(rungs, held)
	}
}

// SubjectLadderLength is how many rungs the branch's ladder has when every one compares the SAME subject with a
// constant: the shape whose answer is a dispatch on that subject, a lookup keyed by it or the language's own
// switch. Zero for a ladder whose rungs test anything else, and zero for a branch that is itself a rung, so one
// ladder is counted once.
func SubjectLadderLength(branch Match) int {
	if !branch.Is(Branch) || branch.IsContinuation() {
		return 0
	}
	rungs := LadderRungs(branch)
	subject := branch.Condition().ComparisonSubject()
	for _, rung := range rungs {
		tested := rung.Condition().ComparisonSubject()
		if !tested.Exists() || !tested.SameSyntax(subject) {
			return 0
		}
	}

	return len(rungs)
}

// BindsItsOwnTest says whether the branch binds a name in its header for its own body to read. A language whose
// grammar answers for itself is asked, since Go files the binding as a statement beside the condition rather
// than inside it; otherwise the condition's own declarations answer, which is where C# and Python write theirs.
func (m Match) BindsItsOwnTest() bool {
	if read := m.grammar().BindsItsOwnTest; read != nil && m.node != nil {
		return read(m)
	}
	condition := m.Condition()

	return declaresAName(append(condition.Descendants(), condition))
}
