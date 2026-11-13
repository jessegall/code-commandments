package contract

import (
	"crypto/sha256"
	"encoding/binary"
)

// Outline is the print of what a file declares for the files that read it: each node's kind and name, its symbol and
// reference, and the types written, returned and resolved, with every function's body (the statements under it) left
// out, so an edit inside a body leaves it as it was. named takes the checkout out of a name, so the same outline prints the same in every
// checkout.
func (f *File) Outline(named func(string) string) uint64 {
	digest := sha256.New()
	var outline func(node *Node)
	outline = func(node *Node) {
		digest.Write([]byte(node.Kind + "\x00" + node.Name + "\x00"))
		if node.Facts != nil {
			digest.Write([]byte(named(node.Symbol + "\x00" + node.Refers + "\x00" + typeText(node.Declared) + "\x00" + typeText(node.Returns) + "\x00")))
		}
		digest.Write([]byte(named(typeText(node.Resolved)) + "\x00"))
		for _, child := range node.Children {
			if node.Answers("function") && (child.Role == "statement" || child.Answers("block")) {
				continue
			}
			outline(child)
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
