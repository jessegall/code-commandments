package bridge

import (
	"bytes"
	"fmt"

	"github.com/jessegall/code-commandments/contract"
)

const DeclaresFlag = "--declares"

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
