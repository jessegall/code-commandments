package shop

import "errors"

// Warehouse counts what is on the shelves and what has been promised away.
type Warehouse struct {
	onHand   map[string]int
	promised map[string]int
	closed   bool
}

// Available is what may still be sold of an item.
func (w *Warehouse) Available(sku string) int {
	// @sin RedundantElse
	if w.closed {
		return 0
	} else {
		return w.onHand[sku] - w.promised[sku]
	}
}

// @fixed RedundantElse

// AvailableTold is the same answer with the else gone: the return above it already says the rest runs only
// when the warehouse is open, so what was inside stands at the method's own level.
func (w *Warehouse) AvailableTold(sku string) int {
	if w.closed {
		return 0
	}

	return w.onHand[sku] - w.promised[sku]
}

// Receive puts a delivery on the shelves, and refuses a count that makes no sense.
func (w *Warehouse) Receive(sku string, count int) error {
	if count <= 0 {
		return errors.New("a delivery of nothing is no delivery")
	}
	w.onHand[sku] += count
	delete(w.promised, sku)

	return nil
}

// @righteous RedundantElse

// Label says how an item should be shelved. Neither branch leaves: both answer, and the else is the other half
// of one decision rather than a line the exit above it already wrote.
func (w *Warehouse) Label(sku string) string {
	label := "shelf"
	if w.promised[sku] > w.onHand[sku] {
		label = "backorder"
	} else {
		label = label + ":" + sku
	}

	return label
}
