// Package layout lays words out on a terminal byte for byte as the PHP tool did: its wrap, its padding and its trimming, so a screen the Go binary prints is the screen a user already knows.
package layout

import (
	"strings"
	"unicode/utf8"
)

const Blank = " \t\n\r\x00\x0B"

func Trim(text string) string {
	return strings.Trim(text, Blank)
}

func TrimRight(text string) string {
	return strings.TrimRight(text, Blank)
}

func TrimLeft(text string) string {
	return strings.TrimLeft(text, Blank)
}

func Width(text string) int {
	return utf8.RuneCountInString(text)
}

func Pad(text string, width int) string {
	short := width - Width(text)

	if short <= 0 {
		return text
	}

	return text + strings.Repeat(" ", short)
}

func Wrap(text string, width int, brk string) string {
	if text == "" {
		return ""
	}

	if len(brk) == 1 {
		return wrapInPlace(text, width, brk[0])
	}

	return wrapJoined(text, width, brk)
}

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

func PadBytes(text string, width int) string {
	return text + strings.Repeat(" ", max(0, width-len(text)))
}

func PadBytesLeft(text string, width int) string {
	return strings.Repeat(" ", max(0, width-len(text))) + text
}
