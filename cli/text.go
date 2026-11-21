package cli

import (
	"os"
	"os/exec"
	"strconv"
	"sync"

	"github.com/jessegall/code-commandments/cli/layout"
)

const (
	assumedWidth = 80

	comfortableWidth = 96
)

var proseMarkers = []string{" ", "\t", "-", "*", "|", "#", ">", "`", "+"}

var TerminalWidth = sync.OnceValue(func() int {
	given := os.Getenv("COLUMNS")

	if given == "" || given == "0" {
		answer, _ := exec.Command("tput", "cols").Output()
		given = layout.Trim(string(answer))
	}

	columns, _ := strconv.Atoi(given)

	if columns <= 20 {
		columns = assumedWidth
	}

	return min(comfortableWidth, columns)
})
