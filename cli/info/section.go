package info

import (
	"strings"

	"github.com/jessegall/code-commandments/cli/layout"
)

const (
	blockHeading = "### "
	section      = "## "
)

// Example is the worked example a skill document gives for the sin: each `###` block whose title names it,
// from its first code fence, prefixed with the language a title names after `— in`.
func Example(document, sin string) string {
	var blocks [][]string
	var current []string
	open := false

	closeBlock := func() {
		if open {
			blocks = append(blocks, current)
		}

		current, open = nil, false
	}

	for _, line := range strings.Split(document, "\n") {
		switch {
		case strings.HasPrefix(line, blockHeading):
			closeBlock()

			if names(line, sin) {
				current, open = []string{line}, true
			}
		case strings.HasPrefix(line, section):
			closeBlock()
		case open:
			current = append(current, line)
		}
	}

	closeBlock()

	var rendered []string

	for _, block := range blocks {
		rendered = append(rendered, code(block))
	}

	return layout.Trim(strings.Join(rendered, "\n\n"))
}

func code(block []string) string {
	title, body := block[0], block[1:]
	_, language, inLanguage := strings.Cut(title, "— in ")

	fence := -1
	for i, line := range body {
		if strings.HasPrefix(line, "```") {
			fence = i

			break
		}
	}

	if fence < 0 {
		return ""
	}

	example := layout.Trim(strings.Join(body[fence:], "\n"))

	if inLanguage {
		return "In " + layout.Trim(strings.Split(language, "— in ")[0]) + ":\n\n" + example
	}

	return example
}

func names(line, sin string) bool {
	title := layout.Trim(line[len(blockHeading):])
	title, _, _ = strings.Cut(title, "— in ")

	for _, part := range strings.Split(title, "·") {
		if layout.Trim(part) == sin {
			return true
		}
	}

	return false
}
