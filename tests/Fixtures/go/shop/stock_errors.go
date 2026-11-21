package shop

// readStockCount checks its error with nil written first: the guard is the same either way round.
func readStockCount(read func() (int, error), warn func(string)) int {
	count, err := read()
	// @sin SwallowCatch
	if nil != err {
	}
	if count < 0 {
		warn("negative stock")
	}

	return count
}
