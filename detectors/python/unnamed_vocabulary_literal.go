package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// UnnamedVocabularyLiteralDetector finds a string literal handed to a parameter other calls fill with a named constant that holds the same string.
type UnnamedVocabularyLiteralDetector struct{}

func init() {
	detectors.Register(catalog.Python, UnnamedVocabularyLiteralDetector{})
}

// Sin is the sin the detector finds.
func (UnnamedVocabularyLiteralDetector) Sin() sins.Sin {
	return pysins.UnnamedVocabularyLiteral{}
}

// Find is every place the sin is committed.
func (UnnamedVocabularyLiteralDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := py.In(codebase).Program
	var findings []engine.Match
	for _, match := range py.In(codebase).WhereCall().Where(engine.As(py.Node.IsEvaluated)).Get() {
		call := py.Node{Match: match}
		for _, argument := range call.Arguments() {
			if _, named := program.ConstantFor(call, argument); named && argument.Node().Literal == "string" {
				findings = append(findings, argument.Match)
			}
		}
		for _, keyword := range call.Keywords() {
			if _, named := program.ConstantFor(call, keyword.Child("value")); named && keyword.Child("value").Node().Literal == "string" {
				findings = append(findings, keyword.Child("value").Match)
			}
		}
	}

	return findings
}
