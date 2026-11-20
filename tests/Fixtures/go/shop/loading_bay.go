package shop

// @sin DuplicateFunction
func loadingBaysByCapacity(bays map[string][]int, floor int) map[string]int {
	usable := make(map[string]int)
	for bay, weights := range bays {
		for _, grams := range weights {
			if grams >= floor {
				usable[bay] += grams
			}
		}
	}
	return usable
}

// @sin NearDuplicateFunction
func countLoadedCrates(crates map[string][]int) int {
	total := 0
	for _, weights := range crates {
		for _, weight := range weights {
			if weight >= 1000 {
				total += 2
			}
		}
	}
	return total
}
