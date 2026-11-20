package shop

import "encoding/json"

type InventoryResult struct {
	Units int
	Held  int
}

type InventoryState struct {
	// @sin PhantomNullable
	Active      *InventoryResult
	Reserve     int
	Lot         string
	Stocktaking bool
}

func (state InventoryState) UnitsAvailable() int {
	if state.Stocktaking {
		return 0
	}
	return state.Active.Units - state.Active.Held - state.Reserve
}

func readInventory(data []byte) (map[string]any, error) {
	var stock map[string]any
	if err := json.Unmarshal(data, &stock); err != nil {
		return nil, err
	}
	delete(stock, "source")
	if stock["counted"] == nil {
		stock["counted"] = false
	}
	// @sin RawDecodedArrayReturn
	return stock, nil
}

// @fixed RawDecodedArrayReturn
func decodeInventory(data []byte) (InventoryResult, error) {
	var result InventoryResult
	err := json.Unmarshal(data, &result)
	return result, err
}

type InventoryCounter interface{ Recount(bins []int) int }
type InventorySupplier interface{ Restock(item string) uint }
type InventoryClock interface{ LabelTime(hour int) string }

// @sin CapabilityProbeHub
type InventoryProbe struct {
	ledger any
	item   string
}

func (inventory InventoryProbe) Missing(counted []int) int {
	if auditor, present := inventory.ledger.(InventoryCounter); present {
		return auditor.Recount(counted)
	}
	return 0
}

func (inventory InventoryProbe) Reserve() uint {
	if supplier, available := inventory.ledger.(InventorySupplier); available {
		return supplier.Restock(inventory.item)
	}
	return 0
}

func (inventory InventoryProbe) Stamp(hour int) string {
	if clock, tracked := inventory.ledger.(InventoryClock); tracked {
		return clock.LabelTime(hour)
	}
	return "recorded by hand"
}

func (inventory InventoryProbe) CountBins(samples <-chan int, expected int) int {
	stock := 0
	for count := range samples {
		stock += count
		if stock >= expected {
			break
		}
	}
	return stock
}
