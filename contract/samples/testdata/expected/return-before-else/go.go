package signs

func Sign(n int) string {
	// @contract kind/if
	// @contract role/if/else -> fallback
	if n < 0 {
		// @contract role/return/values -> [negative]
		return "negative" // @label negative literal
	} else { // @label fallback block
		return "positive"
	}
}
