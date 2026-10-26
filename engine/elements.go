package engine

// HandsItsElementOn says whether the loop hands each item it walks to a call rather than working on the item
// itself: the shape that tells orchestration from envy, in every language.
//
// The reading is the structure, which is the same everywhere — walk the loop's body, take every call that
// mentions the item, skip the method calling itself, and ask whose call it is — and `mine` is the one question
// only a language can answer, because each spells "my own" differently: PHP and Python through `$this` and
// `self`, C# through a field of the type or a method it declares. A loop whose item never reaches a call answers
// no, since nothing was handed anywhere.
//
// It says nothing about whether the loop is envy; that is the detector's reading, and this is the pardon the
// three of them share.
func HandsItsElementOn(loop Match, mine func(call Match) bool) bool {
	element := loop.ElementName()
	if element == "" {
		return false
	}
	self := loop.EnclosingFunction().Name()
	for _, call := range callsIn(loop) {
		if callsItself(call, self) {
			continue
		}
		if !mentions(call, element) {
			continue
		}
		if mine(call) {
			return true
		}
	}

	return false
}

// callsIn are the calls the loop's body makes, each once, however deeply they are written.
func callsIn(loop Match) []Match {
	var made []Match
	for _, statement := range loop.Body() {
		for _, held := range append(statement.Descendants(), statement) {
			if held.Is(Call) {
				made = append(made, held)
			}
		}
	}

	return made
}

// callsItself says whether the call is the function the loop stands in calling itself. Such a call walks the
// collection rather than handing an item to another object, so the work still belongs where the collection is.
func callsItself(call Match, self string) bool {
	return self != "" && call.CalleeName() == self
}

// mentions says whether the call names the item anywhere: handed on as an argument, or the object the call is
// sent to.
func mentions(call Match, element string) bool {
	for _, part := range append(call.Descendants(), call) {
		if part.Is(Identifier) && part.Name() == element {
			return true
		}
	}

	return false
}
