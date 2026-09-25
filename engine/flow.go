package engine

// FlowVerdict is what a forward walk from a value slot found: how many places the value reaches assume it is
// present (a dereference nothing guards, or a slot that cannot be absent) against how many acknowledge it may be
// missing (a null guard or a truthiness test). The caller decides what the counts mean: phantom absence is
// Assume >= 1 with Guard == 0, nothing anywhere admitting it. The zero verdict is the one for a slot nothing
// could be traced from, and answers no to that.
type FlowVerdict struct {
	Assume int
	Guard  int
}
