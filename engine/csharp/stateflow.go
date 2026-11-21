package csharp

import (
)

// StateFlow is how a type's own state moves through its members: which members read which of it, which fields may hold nothing, and every value a field is given.
type StateFlow struct {
	owner Node
	state []string
}

// MemberReads is the state one member reads, by name, sorted.
type MemberReads struct {
	Member string
	Reads  []string
}

