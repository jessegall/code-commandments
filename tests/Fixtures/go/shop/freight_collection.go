package shop

func freightCollectionPrice(invoice FreightArgumentCode) string {
	// @sin ConvertedArgument
	return freightLabel(string(invoice))
}
