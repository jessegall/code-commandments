package contract

import (
	"crypto/sha256"
	"encoding/binary"
)

// Outline is the print of what a file declares for the files that read it: each node's kind, the role it fills, its
// name, form, symbol and reference, and the type it resolves to, with every function's body left out, so an edit inside
// a body leaves it as it was and spreads to no importer (rule 22). named takes the checkout out of a name, so the same
// outline prints the same in every checkout.
func (f *File) Outline(named func(string) string) uint64 {
	digest := sha256.New()
	var outline func(node *Node)
	outline = func(node *Node) {
		digest.Write([]byte(node.Kind + "\x00" + node.role + "\x00"))
		if node.Facts != nil {
			digest.Write([]byte(named(node.Name + "\x00" + node.Form + "\x00" + node.Symbol + "\x00" + node.Refers + "\x00")))
		}
		digest.Write([]byte(named(typeText(node.Resolved)) + "\x00"))
		for _, part := range node.Parts {
			if node.Kind == "function" && part.Role == "body" {
				continue
			}
			for _, child := range part.Nodes {
				outline(child)
			}
		}
	}
	if f.Root != nil {
		outline(f.Root)
	}

	return binary.LittleEndian.Uint64(digest.Sum(nil)[:8])
}

func typeText(written *Type) string {
	if written == nil {
		return ""
	}

	return written.Text
}
