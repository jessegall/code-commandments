package shop

import (
	"fmt"

	"commandments.fixture/shop/shop/shelving"
)

func openingStatus(opens, closes, hour int) string {
	if shelving.Between(opens, closes).Covers(hour) {
		return "open"
	}

	// @sin RepeatedStaticFactory
	return fmt.Sprintf("closed, open %d hours a day", shelving.Between(opens, closes).Hours())
}

// @fixed RepeatedStaticFactory
func openingStatusOnce(opens, closes, hour int) string {
	window := shelving.Between(opens, closes)
	if window.Covers(hour) {
		return "open"
	}

	return fmt.Sprintf("closed, open %d hours a day", window.Hours())
}
