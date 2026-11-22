package shop

import (
	"strings"

	"commandments.fixture/shop/shop/shelving"
)

// @righteous RepeatedStaticFactory
func shelfEither(written string) string {
	if strings.HasSuffix(written, "0") {
		return shelving.Parse(written).Label()
	}

	return shelving.Parse(written).Aisle
}

// @righteous RepeatedStaticFactory
func shelfMoved(written, target string) bool {
	code := written
	before := shelving.Parse(code).Aisle
	code = target

	return before != shelving.Parse(code).Aisle
}

// @righteous RepeatedStaticFactory
func shelfApart(from, to string) bool {
	return shelving.Parse(from).Aisle == shelving.Parse(to).Aisle
}
