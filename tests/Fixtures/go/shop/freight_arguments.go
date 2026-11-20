package shop

type FreightArgumentCode string

type FreightArgument struct {
	label   string
	packed  bool
	insured bool
}

func freightLabel(code string) string {
	return code
}

func freightLabels(first FreightArgumentCode, second FreightArgumentCode) [2]string {
	// @sin ConvertedArgument
	one := freightLabel(string(first))
	// @sin ConvertedArgument
	two := freightLabel(string(second))
	return [2]string{one, two}
}

func freightDetails(value FreightArgument, reading string) string {
	if value.packed {
		return reading
	}
	return value.label
}

func freightReport(first FreightArgument, second FreightArgument) [2]string {
	// @sin DerivedArgument
	one := freightDetails(first, first.label)
	// @sin DerivedArgument
	two := freightDetails(second, second.label)
	return [2]string{one, two}
}

func freightReady(first bool, second bool) bool {
	return first && second
}

func freightAvailability(first FreightArgument, second FreightArgument) [2]bool {
	// @sin ComputedBooleanArgument
	one := freightReady(first.packed, first.insured)
	// @sin ComputedBooleanArgument
	two := freightReady(second.packed, second.insured)
	return [2]bool{one, two}
}

// @sin FlagArgument
func freightStatus(urgent bool, queue chan<- string) {
	if urgent {
		queue <- "pack freight"
	} else {
		queue <- "await insurance"
	}
}

// @sin FilledParameter
func freightFill(value *FreightArgument) {
	value.packed = true
}

func freightRead(value *FreightArgument) bool {
	freightFill(value)
	return value.packed
}

// @sin ParamResolvedFromParam
func freightFind(choices map[string]FreightArgument, key string) string {
	return choices[key].label
}

func freightDisplay(
	value FreightArgument,
	// @sin UnusedParameter
	format string,
) string {
	return value.label
}

// @righteous UnusedParameter
func freightCaption(value FreightArgument, prefix string) string {
	if value.insured {
		return prefix + "insured"
	}
	return prefix + "packed"
}
