package php

import (
	"bytes"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.Freezes(contract.PHP, declaresFrozen, func(_ string, source []byte) bool { return bytes.Contains(source, []byte("Frozen")) })
}

// declaresFrozen is a file holding an attribute group written `#[Frozen]`: the group's first attribute, spelled
// `Frozen` itself, with nothing but whitespace after `#[`.
func declaresFrozen(file *engine.File) bool {
	source, err := file.Source()
	if err != nil {
		return false
	}
	for _, node := range file.File.Nodes() {
		if node.Kind != "annotation" {
			continue
		}
		written := string(source[node.Span.Start:node.Span.End])
		opened := strings.HasSuffix(strings.TrimRight(string(source[:node.Span.Start]), " \t\r\n"), "#[")
		if opened && (written == "Frozen" || strings.HasPrefix(written, "Frozen(")) {
			return true
		}
	}

	return false
}
