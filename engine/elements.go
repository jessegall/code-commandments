package engine

// PassesItsElementOn says whether the loop passes each item it walks on rather than working the item out from
// outside: the shape that tells orchestration from envy, in every language and with no per-language answer.
//
// An item is passed on when it reaches a call with nothing working a value out of it on the way: as the object
// the call is sent to, which is asking it (`line.Total()`), or handed over inside the call's arguments, a string
// interpolation among them (`sheet.AppendLine($"  - {line}")`). Reading one of its members and printing that is
// still passing it on; working an answer out of its members (`$"{line.Price * line.Count}"`, `total +=
// line.Grams`) is not, and that answer belongs on the item's own type.
//
// Where the call's answer is itself worked into something, the item must have gone over whole, as an argument to
// somebody else's call: `$sql .= $this->compileUnion($union)` collects answers to a question the loop's own
// class was asked, which is what a compiler assembling SQL does, while `heaviest = max(heaviest, crate.kilos)`
// and `$sum += $line->total()` reduce the collection from outside and belong on the type that holds it.
//
// Every use of the item must pass, because one use that works the item out is the sin whatever the others do.
// A loop whose item reaches nothing answers no, since it passed it nowhere.
//
// Every use of the item must pass, because one use that works the item out is the sin whatever the others do.
// A loop whose item reaches nothing, and one that only hands the item to the method it stands in, answer no:
// the first passes it nowhere and the second is the walk itself, which belongs where the collection is.
//
// It says nothing about whether the loop is envy; that is the detector's reading, and this is the pardon the
// language detectors share.
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

// usesOf are the places the loop's body names the item.
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

// passedOn says whether this one use of the item is passed on rather than worked out. Two things have to hold.
//
// Nothing may work a value out of the item before it reaches a call: `$"{line.Price * line.Count}"` and
// `total += line.Grams` are the loop doing the item's arithmetic itself, and that belongs on the item's type.
//
// And when the call's ANSWER is then worked into something, the item must have been handed over WHOLE, as an
// argument to a call that somebody OWNS and that is not the item's own: `$sql .= $this->compileUnion($union)`
// collects answers to a question the loop's own class was asked, which is what a compiler assembling SQL does.
// A free function owns nothing, so `heaviest = max(heaviest, crate.kilos)` and
// `$uri = str_replace("{$parameter}", "{$parameter}:{$field}", $uri)` are the loop reducing the collection with
// a tool, not handing the work to a collaborator; an item asked to answer for itself and then reduced
// (`$sum += $line->total()`) is the collection's own question asked from outside.
//
// The call the loop's own function makes to itself is no hand-off either: a method calling itself on a part of
// what it walks is the walk, which belongs where the collection is.
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

// worksOutAbove says whether the answer the call gives is worked into something before the loop's statement is
// over: assigned, added to, concatenated.
func worksOutAbove(call Match) bool {
	for parent := call.Parent(); parent.Exists() && !parent.Is(Loop); parent = parent.Parent() {
		if parent.Computes() {
			return true
		}
	}

	return false
}
