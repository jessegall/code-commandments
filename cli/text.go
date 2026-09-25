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
	// assumedWidth is the width used when the terminal will not say.
	assumedWidth = 80

	// comfortableWidth is as wide as prose is laid out; past it the eye loses the start of the next line.
	comfortableWidth = 96
)

// proseMarkers open a line whose break was deliberate: an indent, a bullet, a table, a heading, a fence.
var proseMarkers = []string{" ", "\t", "-", "*", "|", "#", ">", "`", "+"}

// Wrap wraps text to the terminal, indenting every line after the first by indent so a wrapped paragraph
// stays visibly one thing. Existing line breaks are kept.
func Wrap(text string, indent int) string {
	var lines []string

	for _, line := range strings.Split(text, "\n") {
		wrapped := layout.Wrap(layout.TrimRight(line), max(20, TerminalWidth()-indent), "\n")
		lines = append(lines, strings.Split(wrapped, "\n")...)
	}

	return strings.Join(lines, "\n"+strings.Repeat(" ", indent))
}

// Reflow joins each prose paragraph back into one line before wrapping it, leaving anything with a shape
// of its own (a list, a table, an indented block, a fence) exactly as written.
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

// Heading is a title with a rule after it, so a reader sees where one answer ends and the next begins.
func Heading(title string) string {
	rule := max(4, TerminalWidth()-layout.Width(title)-4)

	return "\n── " + title + " " + strings.Repeat("─", rule)
}

// TerminalWidth is how wide the terminal is, asked once: COLUMNS, else tput, else assumed.
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
