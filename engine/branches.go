package engine

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

func (m Match) BindsItsOwnTest() bool {
	if read := m.grammar().BindsItsOwnTest; read != nil && m.node != nil {
		return read(m)
	}
	condition := m.Condition()

	return declaresAName(append(condition.Descendants(), condition))
}
