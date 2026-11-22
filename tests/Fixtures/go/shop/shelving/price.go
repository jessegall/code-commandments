package shelving

import "strings"

type Price struct {
	Cents    int
	Currency string
}

func PriceOf(cents int, currency string) Price {
	return Price{Cents: max(0, cents), Currency: strings.ToUpper(currency)}
}
