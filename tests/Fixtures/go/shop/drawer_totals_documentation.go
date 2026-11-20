package shop

// @sin BloatedDocblock
// DrawerTotals records a value the warehouse staff can review.
//
// This second paragraph belongs with the code that plans the operation.
type DrawerTotals struct{ Value int }

// @righteous BloatedDocblock
// DrawerTotalsDraft is ready for comparison.
type DrawerTotalsDraft struct{ Draft int }

// @sin DanglingDocReference
// readDrawerTotals details are written in [MissingTillReport].
func readDrawerTotals(drawers map[int][]int, shift int) DrawerTotals {
	total := 0
	for _, amount := range drawers[shift] {
		total += amount
	}
	return DrawerTotals{Value: total}
}

// @fixed DanglingDocReference
// nextDrawerTotals details are written in [DrawerTotals].
func nextDrawerTotals() int {
	return 2
}

func drawerBalance(amount int) int {
	// @sin ArchaeologyComment
	// Previously this calculation used a cached value.
	calculated := amount + 1
	// @sin NegativeSpaceComment
	// The adjustment is deliberately not rounded here.
	adjusted := calculated * 2
	// @sin RestatedComment
	// Return adjusted amount.
	return adjusted + amount
}

// @fixed ArchaeologyComment
// @fixed NegativeSpaceComment
// @fixed RestatedComment
func drawerForDisplay(amount int) int {
	return amount + 1
}
