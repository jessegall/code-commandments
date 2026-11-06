package cli

import (
	"os"
	"os/exec"
	"strconv"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/cli/layout"
)

const (
	assumedWidth = 80

	comfortableWidth = 96
)

var proseMarkers = []string{" ", "\t", "-", "*", "|", "#", ">", "`", "+"}

func Wrap(text string, indent int) string {
	var lines []string

	for _, line := range strings.Split(text, "\n") {
		wrapped := layout.Wrap(layout.TrimRight(line), max(20, TerminalWidth()-indent), "\n")
		lines = append(lines, strings.Split(wrapped, "\n")...)
	}

	return strings.Join(lines, "\n"+strings.Repeat(" ", indent))
}

func Reflow(text string, indent int) string {
	var blocks []string

	for _, block := range strings.Split(text, "\n\n") {
		lines := strings.Split(strings.Trim(block, "\n"), "\n")

		if !isProse(lines) {
			blocks = append(blocks, block)

			continue
		}

		for i, line := range lines {
			lines[i] = layout.Trim(line)
		}

		blocks = append(blocks, strings.Join(lines, " "))
	}

	return Wrap(strings.Join(blocks, "\n\n"), indent)
}

func isProse(lines []string) bool {
	for _, line := range lines {
		for _, marker := range proseMarkers {
			if strings.HasPrefix(line, marker) {
				return false
			}
		}
	}

	return true
}

func Heading(title string) string {
	rule := max(4, TerminalWidth()-layout.Width(title)-4)

	return "\n── " + title + " " + strings.Repeat("─", rule)
}

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
