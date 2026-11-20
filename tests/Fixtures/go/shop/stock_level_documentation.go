package shop

import "fmt"

// @sin BloatedDocblock
// StockLevel is input to an inventory transfer.
//
// The inventory desk enters the quantity before the morning audit, after the previous tally is archived.
type StockLevel struct {
	Bin      string
	Count    uint64
	Reserved uint64
}

// @righteous BloatedDocblock
// StockLevelDraft is an unsigned transfer request.
type StockLevelDraft struct{ Draft uint64 }

// @sin DanglingDocReference
// validateStockLevel has the limits listed by [MissingStockLedger].
func validateStockLevel(level StockLevel) error {
	switch {
	case level.Bin == "":
		return fmt.Errorf("stock level needs a bin")
	case level.Reserved > level.Count:
		return fmt.Errorf("bin %q has fewer units than reserved", level.Bin)
	default:
		return nil
	}
}

// @fixed DanglingDocReference
// availableStock reports the unreserved quantity in [StockLevel].
func availableStock(level StockLevel) uint64 {
	return level.Count - level.Reserved
}

func countStock(level StockLevel, held map[string]uint64) uint64 {
	// @sin ArchaeologyComment
	// Previously every aisle counted stock in separate files.
	if present, found := held[level.Bin]; found {
		return present
	}
	// @sin NegativeSpaceComment
	// A stock adjustment of zero is not a typo.
	available := availableStock(level)
	// @sin RestatedComment
	// Return available.
	return available
}
