package shop

type shippingAddress struct {
	street   string
	district string
	postcode string
}

// @righteous ArrayBag
func namedAddressField(items map[string]string, key string) string {
	return items[key]
}

// @fixed ArrayBag
// @fixed ArrayReturnBag
func resolvedAddress(street string, district string, postcode string) shippingAddress {
	return shippingAddress{street: street, district: district, postcode: postcode}
}

// @righteous ArrayReturnBag
func addressTemplate() shippingAddress {
	return shippingAddress{street: "Mill Road", district: "Eastern", postcode: "10001"}
}

func addressKey(
	// @righteous ArrayBagParameter
	fields map[string]string,
) int {
	return len(fields)
}

// @fixed ArrayBagParameter
func addressText(address shippingAddress) string {
	return address.street + ", " + address.district
}

// @righteous KeyedLookupEnvy
func findAddress(addresses map[string]shippingAddress, postalCode string) shippingAddress {
	if len(addresses) == 0 {
		return shippingAddress{}
	}
	return addresses[postalCode]
}

// @righteous FeatureEnvy
func addressDistrict(address shippingAddress) string {
	return address.district
}

// @fixed FeatureEnvy
func (a shippingAddress) Caption() string {
	return a.street + " " + a.district + " " + a.postcode
}

type addressCounter struct {
	active int
	label  string
}

// @righteous CoupledFields
func (a *addressCounter) Rename(label string, active int) int {
	a.active = active
	a.label = label
	return a.active
}

// @righteous BareStatePredicate
func (a *addressCounter) IsOpen() bool { return a.active > 0 }

// @righteous NarratedCommand
func (a *addressCounter) Clear() { a.label = "" }

// @righteous MutableStaticState
// @fixed MutableStaticState
func (a *addressCounter) Tally() { a.active = a.active + 1 }

// @righteous ScratchStateRestore
// @fixed ScratchStateRestore
func (a addressCounter) Inspect(prefix string) string {
	return prefix + a.label
}

// @righteous MutableValueObject
// @fixed MutableValueObject
func (a shippingAddress) WithStreet(street string) shippingAddress {
	return shippingAddress{street: street, district: a.district, postcode: a.postcode}
}

func (a shippingAddress) Equals(other shippingAddress) bool {
	return a.postcode == other.postcode
}

type historicalAddresses struct{ rows []shippingAddress }

// @righteous HandedOutCollection
func (h historicalAddresses) First() shippingAddress { return h.rows[0] }

// @righteous DataClump
// @fixed DataClump
func (h historicalAddresses) Includes(address shippingAddress) bool {
	return len(h.rows) > 0 && h.rows[0].Equals(address)
}
