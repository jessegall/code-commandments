package core

// @sin NamespaceDependency
import inventoryView "commandments.fixture/shop/layers/inventory/view"

type ShelfLabel struct {
	Aisle int
	Units uint
}

func InventoryLabels(rows []ShelfLabel, print func(string, int, uint) error) error {
	for _, row := range rows {
		if row.Units == 0 {
			continue
		}
		if err := print(inventoryView.Caption, row.Aisle, row.Units); err != nil {
			return err
		}
	}
	return nil
}
