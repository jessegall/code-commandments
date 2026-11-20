package shop

// @sin DuplicateFunction
func unloadingBaysByCapacity(bays map[string][]int, floor int) map[string]int {
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
func estimateUnloadCapacity(pallets map[string][]int) int {
	capacity := 0
	for _, weights := range pallets {
		for _, weight := range weights {
			if weight >= 350 {
				capacity += 3
			}
		}
	}
	return capacity
}
