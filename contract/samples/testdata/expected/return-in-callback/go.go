package visit

func Visit() {
	// @contract kind/function
	do := func(value int) {
		// @contract kind/return
		// @contract role/return/values -> []
		return
	}
	do(1)
}
