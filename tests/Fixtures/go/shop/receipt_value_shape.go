package shop

import (
	"fmt"
	"maps"
)

var receiptCount int

func receiptTick(copies int) {
	for copy := 0; copy < copies; copy++ {
		// @sin MutableStaticState
		receiptCount += copy + 1
	}
}

func discardsDeposit(
	// @sin ArrayBag
	slips map[string]int,
) bool {
	if slips["deposit"] == 0 {
		return true
	}
	return slips["collected"] < slips["cancellation"]
}

func receiptWeight(
	// @sin ArrayBagParameter
	lines map[string]int,
) int {
	total := 0
	for _, weight := range lines {
		total += weight
	}
	return total
}

func currentReceiptWeight(room int) bool {
	first := receiptWeight(map[string]int{"paper": 8, "staple": 2})
	return first+receiptWeight(map[string]int{"paper": 12, "staple": 3}) < room
}

func receiptDefaults(net int) map[string]int {
	if net < 0 {
		return nil
	}
	tax := net / 10
	// @sin ArrayReturnBag
	return map[string]int{"gross": net + tax, "vat": tax, "adjusted": net}
}

type ReceiptDelivery struct{ neighbourhood string }

type ReceiptShapeFields struct {
	copies   int
	counter  int
	delivery ReceiptDelivery
}

// @sin FeatureEnvy
func receiptDescription(receipt ReceiptShapeFields) string {
	if receipt.copies <= 0 {
		return fmt.Sprint(receipt.counter)
	}
	return fmt.Sprintf("%d copies at counter %d for %s", receipt.copies, receipt.counter, receipt.delivery.neighbourhood)
}

// @sin KeyedLookupEnvy
func receiptCounterName(counters map[int]string, receipt ReceiptShapeFields) string {
	return counters[receipt.counter]
}

type ReceiptShapePrinter struct{}
type ReceiptShapeValidator struct{}

// @sin DataClump
func (ReceiptShapePrinter) Print(copies int, counter int, store string) string {
	return fmt.Sprintf("%s counter %d, %d copies", store, counter, copies)
}

// @sin DataClump
func (ReceiptShapeValidator) Check(copies int, counter int, store string) bool {
	if store == "" || counter < 1 {
		return false
	}
	return copies > 0 && copies < 20
}

type ReceiptShapeState struct {
	counter int
	slip    string
}

// @sin CoupledFields
func (state *ReceiptShapeState) Accept(counter int, slip string) bool {
	state.counter = counter
	state.slip = slip
	return state.counter > 0 && len(state.slip) > 3
}

// @sin BareStatePredicate
func (state ReceiptShapeState) Opens() bool {
	return state.counter >= 1
}

// @sin NarratedCommand
func (state *ReceiptShapeState) Prints() {
	state.counter += 1
	state.slip = fmt.Sprintf("receipt-%04d", state.counter)
}

// @sin ScratchStateRestore
func (state *ReceiptShapeState) ProbeSlip(prefix string) string {
	kept := state.slip
	state.slip = prefix + state.slip
	displayed := fmt.Sprintf("%d %s", state.counter, state.slip)
	state.slip = kept
	return displayed
}

type ReceiptShapeValue struct {
	amount   int
	currency string
}

func (receipt ReceiptShapeValue) Equals(other ReceiptShapeValue) bool {
	return receipt.amount == other.amount && receipt.currency == other.currency
}

// @sin MutableValueObject
func (receipt *ReceiptShapeValue) AddDeposit(paid int) {
	if paid > 0 {
		receipt.amount += paid
	}
}

type ReceiptShapeShelf struct {
	values map[int]ReceiptShapeValue
}

func (shelf ReceiptShapeShelf) ByCounter() map[int]ReceiptShapeValue {
	// @sin HandedOutCollection
	return shelf.values
}

// @fixed HandedOutCollection
func (shelf ReceiptShapeShelf) Snapshot() map[int]ReceiptShapeValue {
	return maps.Clone(shelf.values)
}
