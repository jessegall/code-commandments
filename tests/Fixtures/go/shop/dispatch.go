package shop

// Crate is what a bay sends out.
type Crate struct {
	Packed   bool
	Labelled bool
	Weight   int
}

// Bay is where crates are loaded.
type Bay struct {
	crates []Crate
	closed bool
}

// Dispatch sends out every crate the bay is holding.
func (b *Bay) Dispatch(bays []*Bay) int {
	sent := 0
	for _, bay := range bays {
		if !bay.closed {
			for _, crate := range bay.crates {
				// @sin DeepNesting
				if crate.Packed {
					sent++
				}
			}
		}
	}

	return sent
}

// @fixed DeepNesting

// Sent is what Dispatch counts, with each level guarded away instead of nested.
func (b *Bay) Sent(bays []*Bay) int {
	sent := 0
	for _, bay := range bays {
		if bay.closed {
			continue
		}

		sent += bay.packed()
	}

	return sent
}

// packed is how many of the bay's own crates are ready, which is the decision the nesting buried.
func (b *Bay) packed() int {
	packed := 0
	for _, crate := range b.crates {
		if !crate.Packed {
			continue
		}

		packed++
	}

	return packed
}

// @righteous DeepNesting

// Weigh adds up what the bay holds. Three levels is as deep as a reader follows, so this is no finding.
func (b *Bay) Weigh(bays []*Bay) int {
	weight := 0
	for _, bay := range bays {
		for _, crate := range bay.crates {
			if crate.Labelled {
				weight += crate.Weight
			}
		}
	}

	return weight
}
