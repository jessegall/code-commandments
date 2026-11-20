package shop

type checkoutLabel struct {
	code  string
	ready bool
	paid  bool
}

// @fixed ConvertedArgument
func composeCheckoutLabel(label checkoutLabel) string {
	return label.code + " checkout"
}

// @righteous ConvertedArgument
func decorateCheckoutLabel(label checkoutLabel) string {
	return composeCheckoutLabel(label)
}

// @fixed DerivedArgument
func paidCheckoutLabel(label checkoutLabel) string {
	if label.paid {
		return label.code + " paid"
	}
	return label.code
}

// @righteous DerivedArgument
func customCheckoutLabel(label checkoutLabel, prefix string) string {
	return prefix + paidCheckoutLabel(label)
}

// @fixed ComputedBooleanArgument
func canPrintCheckoutLabel(label checkoutLabel) bool {
	return label.paid && label.ready
}

// @righteous ComputedBooleanArgument
func confirmCheckoutLabel(label checkoutLabel) bool {
	return canPrintCheckoutLabel(label)
}

// @righteous FlagArgument
func shouldQueueLabel(enabled bool, waiting int) bool {
	return enabled && waiting > 0
}

// @fixed FlagArgument
func enqueueLabel(count int) int {
	return count + 1
}

// @fixed FilledParameter
func payCheckoutLabel(label checkoutLabel) checkoutLabel {
	label.paid = true
	return label
}

// @righteous FilledParameter
func issueCheckoutLabel(label checkoutLabel) string {
	return paidCheckoutLabel(payCheckoutLabel(label))
}

// @fixed ParamResolvedFromParam
func checkoutCode(label checkoutLabel) string {
	return label.code
}

// @righteous ParamResolvedFromParam
func lookupCheckoutCode(labels map[string]checkoutLabel, key string) string {
	if len(labels) == 0 {
		return ""
	}
	return checkoutCode(labels[key])
}

// @righteous UnusedParameter
func prefixedCheckoutCode(label checkoutLabel, prefix string) string {
	return prefix + label.code
}

// @fixed UnusedParameter
func plainCheckoutCode(label checkoutLabel) string {
	return label.code
}
