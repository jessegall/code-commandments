package bridge

import (
	"bytes"
	"fmt"

	"github.com/jessegall/code-commandments/contract"
)

// DeclaresFlag is the one ask a bridge takes besides a tree: given it and nothing else, the bridge writes what
// its parser CAN emit (contract.Declaration) as a single JSON object and exits.
const DeclaresFlag = "--declares"

// Declares is what the bridge says its parser can emit, one declaration per language it writes. A recorded stream
// says only what some fixture happened to contain, so a kind no fixture holds reads as a kind the language has
// not got; this asks the parser instead.
//
// The bridge is run for it, not served: the answer is the same every run of a given bridge, and nothing about it
// is per-request.
func Declares(command []string) ([]contract.Declaration, error) {
	var written, failure bytes.Buffer
	process, stop := launch(command, DeclaresFlag)
	defer stop()
	process.Stdout = &written
	process.Stderr = &failure

	if err := process.Run(); err != nil {
		return nil, Failed(command, err, failure.String())
	}

	declared, err := contract.ReadDeclarations(&written)
	if err != nil {
		return nil, fmt.Errorf("%s: %w", command[0], err)
	}

	return declared, nil
}
