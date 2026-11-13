package hooks

const helpersCLIKey = "workers_cli"

func helpersRunTheCLI(moment Moment) bool {
	said, named := moment.Settings[helpersCLIKey]
	if named {
		return switchedOn(said)
	}

	return helpersChosenToRunTheCLI()
}

func helpersChosenToRunTheCLI() bool {
	chosen, given := chosenSettings()
	if !given {
		return false
	}

	said, named := chosen[helpersCLIKey]

	return named && switchedOn(said)
}
