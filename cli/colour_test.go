package cli

import (
	"bytes"
	"testing"
)

func TestColourIsDroppedWhereNoTerminalReadsIt(t *testing.T) {
	t.Setenv("CLICOLOR_FORCE", "")

	var captured bytes.Buffer
	stream := Coloured(&captured)

	for _, part := range []string{"\033[36msrc/Cart.php:3\033[0m  ", "type Order  \033", "[2m[MirroredServerTypeDetector]\033[", "0m\n"} {
		if n, err := stream.Write([]byte(part)); err != nil || n != len(part) {
			t.Fatalf("wrote %d of %d: %v", n, len(part), err)
		}
	}

	if got, want := captured.String(), "src/Cart.php:3  type Order  [MirroredServerTypeDetector]\n"; got != want {
		t.Errorf("wrote %q, want %q", got, want)
	}
}

func TestNoColourIsAskedForByNoColour(t *testing.T) {
	t.Setenv("CLICOLOR_FORCE", "1")
	t.Setenv("NO_COLOR", "1")

	var captured bytes.Buffer
	Coloured(&captured).Write([]byte("\033[1m138 sins\033[0m"))

	if captured.String() != "138 sins" {
		t.Errorf("wrote %q", captured.String())
	}
}

func TestColourIsKeptWhereItIsForced(t *testing.T) {
	t.Setenv("NO_COLOR", "")
	t.Setenv("CLICOLOR_FORCE", "1")

	var captured bytes.Buffer
	Coloured(&captured).Write([]byte("\033[1m138 sins\033[0m"))

	if captured.String() != "\033[1m138 sins\033[0m" {
		t.Errorf("wrote %q", captured.String())
	}
}
