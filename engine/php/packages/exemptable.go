package packages

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// By is where a finding is looked up when a detector asks whether a package excuses it.
type By int

// The places a finding is looked up by.
const (
	EnclosingClass By = iota
	EnclosingMethod
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

// Exempt drops every finding a package excuses under an exemption the detector honours.
func Exempt(codebase *engine.Codebase, detector Exemptable, findings []engine.Match) []engine.Match {
	return slices.DeleteFunc(slices.Clone(findings), func(finding engine.Match) bool {
		return excused(codebase, detector, finding)
	})
}

func excused(codebase *engine.Codebase, detector Exemptable, finding engine.Match) bool {
	for _, exemption := range detector.Exemptions() {
		for _, by := range exemption.By {
			method := ""
			if by == EnclosingMethod {
				method = php.EnclosingFunctionName(finding)
			}
			if Excuses(codebase, exemption.Tag, php.EnclosingClassName(finding), method) {
				return true
			}
		}
	}

	return false
}
