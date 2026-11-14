package hooks

const helpersCLIKey = "workers_cli"

func helpersRunTheCLI(moment Moment) bool {
	return switchedOn(chosenAt(moment)[helpersCLIKey])
}
