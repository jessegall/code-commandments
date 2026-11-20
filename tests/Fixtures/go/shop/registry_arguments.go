package shop

type RegistrationArgumentCode int

type RegistrationArgument struct {
	index    int
	signed   bool
	verified bool
}

func registryLabel(number int) int {
	return number + 1000
}

func registryLabels(numbers []RegistrationArgumentCode) int {
	if len(numbers) < 2 {
		return 0
	}
	// @sin ConvertedArgument
	initial := registryLabel(int(numbers[0]))
	// @sin ConvertedArgument
	return initial - registryLabel(int(numbers[len(numbers)-1]))
}

func indexRegistration(form RegistrationArgument, position int) int {
	if form.verified {
		return position
	}
	return -1
}

func indexAllRegistrations(forms []RegistrationArgument) int {
	last := -1
	for _, form := range forms {
		// @sin DerivedArgument
		last = indexRegistration(form, form.index)
	}
	return last
}

func firstRegistration(expected RegistrationArgument, alternate RegistrationArgument) int {
	if expected.signed {
		// @sin DerivedArgument
		return indexRegistration(expected, expected.index)
	}
	// @sin DerivedArgument
	return indexRegistration(alternate, alternate.index)
}

func approveRegistration(signed bool, verified bool) bool {
	return signed && verified
}

func firstApprovedRegistration(forms []RegistrationArgument) int {
	for index, form := range forms {
		// @sin ComputedBooleanArgument
		if approveRegistration(form.signed, form.verified) {
			return index
		}
	}
	return -1
}

func registerIfReady(form RegistrationArgument) string {
	// @sin ComputedBooleanArgument
	if approveRegistration(form.signed, form.verified) {
		return "registration verified"
	}
	return "waiting for verification"
}

// @sin FlagArgument
func registryStatus(enabled bool, labels map[string]int) {
	if enabled {
		labels["ready registrations"] = len(labels)
	} else {
		delete(labels, "ready registrations")
	}
}

// @sin FilledParameter
func verifyRegistration(form *RegistrationArgument) {
	form.verified = form.signed
}

func verificationResult(form *RegistrationArgument) string {
	verifyRegistration(form)
	if form.verified {
		return "verified"
	}
	return "signature missing"
}

// @sin ParamResolvedFromParam
func recordedRegistration(forms map[int]RegistrationArgument, index int) bool {
	form := forms[index]
	return form.signed && form.verified
}

func lastRegistration(
	forms []RegistrationArgument,
	// @sin UnusedParameter
	rank int,
) int {
	last := -1
	for _, form := range forms {
		if form.index > last {
			last = form.index
		}
	}
	return last
}
