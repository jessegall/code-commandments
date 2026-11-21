package packages

import ()

// By is where a finding is looked up when a detector asks whether a package excuses it.
type By int

const (
	EnclosingClass By = iota
	EnclosingMethod
	CallbackConsumer
	CallbackResultConsumer
	ReturnConsumer
)

// Exemption is a tag a detector honours, and where it looks a finding up; none means the detector asks itself.
type Exemption struct {
	Tag Tag
	By  []By
}

// Exemptable is a detector that honours exemptions.
type Exemptable interface {
	Exemptions() []Exemption
}
