package engine

const (
	conditionParts    = 3
	conditionSubjects = 2
)

func (m Match) IsRenderCondition() bool {
	reads := m.grammar().RenderCondition

	return m.Exists() && reads != nil && reads(m.Parent()).node == m.node
}

func (m Match) IsRenderConditionPart() bool {
	part := m.Grouped()
	for part.Parent().Is(Conjunction) {
		part = part.Parent().Grouped()
	}

	return part.IsRenderCondition()
}

func (m Match) OnlyLeaves() bool {
	return onlyLeaves(m)
}

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
