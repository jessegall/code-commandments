package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// UnnamedVocabularyLiteralDetector finds a string written where a constant of the codebase's own already names it: a parameter other calls fill by name, outside the tests.
type UnnamedVocabularyLiteralDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, UnnamedVocabularyLiteralDetector{})
}

// Sin is the sin the detector finds.
func (UnnamedVocabularyLiteralDetector) Sin() sins.Sin {
	return cssins.UnnamedVocabularyLiteral{}
}

// Find is every place the sin is committed.
func (UnnamedVocabularyLiteralDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program
	var findings []engine.Match
	for _, match := range cs.In(codebase).WhereCall().Get() {
		call := cs.Node{Match: match}
		if !call.PassesByPosition() || call.IsInTest() {
			continue
		}
		for position, literal := range call.Arguments() {
			if literal.Is("StringLiteralExpression") && program.ConstantNaming(call, position, literal.Text()) != "" {
				findings = append(findings, literal.Match)
			}
		}
	}

	return findings
}
