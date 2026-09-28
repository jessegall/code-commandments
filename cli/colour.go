package cli

import (
	"io"
	"os"
)

// Coloured is the stream as the tool writes to it: coloured on a terminal, or wherever CLICOLOR_FORCE asks for colour,
// and with every colour and style sequence dropped elsewhere, so a log, a pipe or a plugin's capture reads plain
// text. NO_COLOR drops it everywhere.
func Coloured(stream io.Writer) io.Writer {
	if os.Getenv("NO_COLOR") == "" && (isTerminal(stream) || os.Getenv("CLICOLOR_FORCE") != "" && os.Getenv("CLICOLOR_FORCE") != "0") {
		return stream
	}

	return &plain{out: stream}
}

// plain writes what it is given less every escape sequence, one that a write splits included.
type plain struct {
	out   io.Writer
	state escapeState
}

// escapeState is where a plain writer stands in the bytes it reads: in text, just past an escape, or inside a
// control sequence, which ends at its final byte.
type escapeState int

const (
	inText escapeState = iota
	pastEscape
	inSequence
)

// escape opens every colour and style sequence.
const escape = 0x1b

func (p *plain) Write(written []byte) (int, error) {
	kept := make([]byte, 0, len(written))

	for _, character := range written {
		switch {
		case p.state == inSequence:
			if character >= 0x40 && character <= 0x7e {
				p.state = inText
			}
		case p.state == pastEscape:
			p.state = inText
			if character == '[' {
				p.state = inSequence
			}
		case character == escape:
			p.state = pastEscape
		default:
			kept = append(kept, character)
		}
	}

	if _, err := p.out.Write(kept); err != nil {
		return 0, err
	}

	return len(written), nil
}
