package shop

func total(items map[string]int, limit int) int {
	sum := 0
	// @contract kind/loop
	// @contract role/loop/initializer -> [indexInit]
	// @contract role/loop/condition -> indexLess
	// @contract role/loop/steps -> [indexStep]
	// @contract role/loop/body -> indexBody
	// @label indexInit assignment 6
	// @label indexLess comparison
	// @label indexStep assignment 33
	// @label indexBody block
	// @contract fact/assignment/mode at 33 = "increment"
	for index := 0; index < limit; index++ {
		sum += index
	}
	// @contract kind/loop
	// @contract role/loop/bindings -> [keyBinding, valueBinding]
	// @contract role/loop/iterable -> itemsRef
	// @label keyBinding binding 6
	// @label valueBinding binding 11
	// @label itemsRef reference
	for key, value := range items {
		sum += len(key) + value
	}
	// @contract kind/block
	// @contract role/block/statements -> [panicStatement]
	if sum < 0 {
		// @contract kind/throw
		// @contract role/throw/value -> message
		// @label panicStatement throw
		// @label message literal
		panic("negative")
	}
	return sum
}
