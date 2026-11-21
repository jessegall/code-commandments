package vue

import (
)

const switchCases = 2

const SwitchCaseTag = "SwitchCase"

// EqualityTest is a condition that tests one subject against a literal: status === 'paid'.
type EqualityTest struct {
	Subject string
	Key     string
}

// SwitchCaseChain is a v-if and the v-else-ifs after it that equality-test one subject, and a v-else that ends it: one decision written as several conditionals.
type SwitchCaseChain struct {
	Subject  string
	Head     Element
	Branches []SwitchCaseBranch
}

// SwitchCaseBranch is one element of a chain and the key it matches; the v-else that ends a chain matches none.
type SwitchCaseBranch struct {
	Element
	Key      string
	Fallback bool
}

