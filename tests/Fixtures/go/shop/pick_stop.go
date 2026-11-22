package shop

import "commandments.fixture/shop/shop/shelving"

func pickStop(written string) string {
	// @sin RepeatedStaticFactory
	return shelving.Parse(written).Aisle + " at " + shelving.Parse(written).Label()
}

// @fixed RepeatedStaticFactory
func pickStopOnce(written string) string {
	code := shelving.Parse(written)

	return code.Aisle + " at " + code.Label()
}
