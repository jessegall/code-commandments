package engine

// FlowVerdict is what a forward walk from a value slot found: how many places the value reaches assume it is present (a dereference nothing guards, or a slot that cannot be absent) against how many acknowledge it may be missing (a null guard or a truthiness test).
type FlowVerdict struct {
	Assume int
	Guard  int
}
