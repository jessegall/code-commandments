package shop

import (
	"fmt"

	"commandments.fixture/shop/shop/shelving"
)

func describeLine(cents int, currency string, quantity int) string {
	unit := shelving.PriceOf(cents, currency).Cents
	total := unit * quantity

	// @sin RepeatedStaticFactory
	return fmt.Sprintf("%d x %d %s = %d", quantity, unit, shelving.PriceOf(cents, currency).Currency, total)
}

// @fixed RepeatedStaticFactory
func describeLineOnce(cents int, currency string, quantity int) string {
	price := shelving.PriceOf(cents, currency)

	return fmt.Sprintf("%d x %d %s = %d", quantity, price.Cents, price.Currency, price.Cents*quantity)
}
