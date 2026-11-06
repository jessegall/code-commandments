package engine

func PassesItsElementOn(loop Match) bool {
	element := loop.ElementName()
	if element == "" {
		return false
	}
	uses := usesOf(loop, element)
	if len(uses) == 0 {
		return false
	}
	self := loop.EnclosingFunction().Name()
	for _, use := range uses {
		if !passedOn(use, self) {
			return false
		}
	}

	return true
}

func usesOf(loop Match, element string) []Match {
	var named []Match
	for _, statement := range loop.Body() {
		for _, part := range append(statement.Descendants(), statement) {
			if part.Is(Identifier) && part.Name() == element {
				named = append(named, part)
			}
		}
	}

	return named
}

func passedOn(use Match, self string) bool {
	whole, held := true, use
	for parent := use.Parent(); parent.Exists() && !parent.Is(Loop); parent = parent.Parent() {
		if parent.Computes() {
			return false
		}
		if !parent.Is(Call) {
			if parent.Is(MemberAccess) {
				whole = false
			}
			held = parent

			continue
		}
		if self != "" && parent.CalleeName() == self {
			return false
		}
		receiver := parent.Receiver()
		asked := receiver.Node() == held.Node()
		if !worksOutAbove(parent) {
			return true
		}

		return whole && !asked && receiver.Exists()
	}

	return false
}

func worksOutAbove(call Match) bool {
	for parent := call.Parent(); parent.Exists() && !parent.Is(Loop); parent = parent.Parent() {
		if parent.Computes() {
			return true
		}
	}

	return false
}
