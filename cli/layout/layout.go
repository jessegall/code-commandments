// Package layout lays words out on a terminal byte for byte as the PHP tool did: its wrap, its padding and
// its trimming, so a screen the Go binary prints is the screen a user already knows.
package layout

import (
	"strings"
	"unicode/utf8"
)

// Blank is every byte PHP's trim family strips when given no list of its own.
const Blank = " \t\n\r\x00\x0B"

// Trim strips Blank from both ends.
func Trim(text string) string {
	return strings.Trim(text, Blank)
}

// TrimRight strips Blank from the end.
func TrimRight(text string) string {
	return strings.TrimRight(text, Blank)
}

// TrimLeft strips Blank from the start.
func TrimLeft(text string) string {
	return strings.TrimLeft(text, Blank)
}

// Width is how many characters a string shows, not how many bytes it takes: an em dash is one.
func Width(text string) int {
	return utf8.RuneCountInString(text)
}

// Pad fills text with spaces on the right until it is width characters wide.
func Pad(text string, width int) string {
	short := width - Width(text)

	if short <= 0 {
		return text
	}

	return text + strings.Repeat(" ", short)
}

// Wrap breaks text at spaces so no line runs past width bytes, joining the lines with brk. A word longer
// than the width is never cut; it keeps a line of its own. Widths count bytes, as PHP's wordwrap does.
func Wrap(text string, width int, brk string) string {
	if text == "" {
		return ""
	}

	if len(brk) == 1 {
		return wrapInPlace(text, width, brk[0])
	}

	return wrapJoined(text, width, brk)
}

// wrapInPlace turns chosen spaces into the one-byte break, leaving every other byte where it was.
func wrapInPlace(text string, width int, brk byte) string {
	out := []byte(text)
	lastStart, lastSpace := 0, 0

	for current := 0; current < len(text); current++ {
		switch {
		case text[current] == brk:
			lastStart, lastSpace = current+1, current+1
		case text[current] == ' ':
			if current-lastStart >= width {
				out[current] = brk
				lastStart = current + 1
			}

			lastSpace = current
		case current-lastStart >= width && lastStart != lastSpace:
			out[lastSpace] = brk
			lastStart = lastSpace + 1
		}
	}

	return string(out)
}

// wrapJoined copies the text line by line, putting the break between lines in place of the space.
func wrapJoined(text string, width int, brk string) string {
	var out strings.Builder
	lastStart, lastSpace := 0, 0
	current := 0

	for ; current < len(text); current++ {
		switch {
		case text[current] == brk[0] && current+len(brk) < len(text) && strings.HasPrefix(text[current:], brk):
			out.WriteString(text[lastStart : current+len(brk)])
			current += len(brk) - 1
			lastStart, lastSpace = current+1, current+1
		case text[current] == ' ':
			if current-lastStart >= width {
				out.WriteString(text[lastStart:current])
				out.WriteString(brk)
				lastStart = current + 1
			}

			lastSpace = current
		case current-lastStart >= width && lastStart < lastSpace:
			out.WriteString(text[lastStart:lastSpace])
			out.WriteString(brk)
			lastStart, lastSpace = lastSpace+1, lastSpace+1
		}
	}

	if lastStart != current {
		out.WriteString(text[lastStart:current])
	}

	return out.String()
}

// PadBytes fills text with spaces on the right to width bytes, as PHP's `%-Ns` and str_pad do.
func PadBytes(text string, width int) string {
	return text + strings.Repeat(" ", max(0, width-len(text)))
}

// PadBytesLeft fills text with spaces on the left to width bytes, as PHP's `%Ns` does.
func PadBytesLeft(text string, width int) string {
	return strings.Repeat(" ", max(0, width-len(text))) + text
}
