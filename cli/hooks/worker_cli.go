package hooks

const workerCLIKey = "workers_cli"

func workersRunTheCLI(moment Moment) bool {
	said, named := moment.Settings[workerCLIKey]
	if named {
		return switchedOn(said)
	}

	return workersChosenToRunTheCLI()
}

func workersChosenToRunTheCLI() bool {
	chosen, given := chosenSettings()
	if !given {
		return false
	}

	said, named := chosen[workerCLIKey]

	return named && switchedOn(said)
}
