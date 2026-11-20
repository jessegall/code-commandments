package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// BlankStringDefault finds a blank string default stands for a missing required value.
type BlankStringDefault struct{}

func (BlankStringDefault) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.TestsBlankDefault).Get()
}
func (BlankStringDefault) Sin() Text {
	return Text{Name: "blank-string-default", Skill: skills.Absence{}, Description: "A blank string default stands for a missing required value", Rule: "Require the text or give absence its own type.", Suggestion: "Require the text or give absence its own type.", Formerly: map[catalog.Engine]Former{catalog.Python: {Sin: "python-blank-string-default"}, catalog.CSharp: {Sin: "csharp-blank-string-default"}}}
}
func (BlankStringDefault) CrossFile() {}

// DeNulledFinder finds every caller takes a nullable result as present.
type DeNulledFinder struct{}

func (DeNulledFinder) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.DiscardsOptionalReturn).Get()
}
func (DeNulledFinder) Sin() Text {
	return Text{Name: "de-nulled-finder", Skill: skills.Absence{}, Description: "Every caller takes a nullable result as present", Rule: "Make the finder provide a value or report the miss.", Suggestion: "Make the finder provide a value or report the miss.", Formerly: map[catalog.Engine]Former{catalog.Python: {Sin: "python-de-nulled-finder"}, catalog.CSharp: {Sin: "csharp-de-nulled-finder"}}}
}
func (DeNulledFinder) CrossFile() {}

// ManufacturedFakeFill finds an empty scalar fills a slot that expects a meaningful value.
type ManufacturedFakeFill struct{}

func (ManufacturedFakeFill) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.ManufacturesArgument).Get()
}
func (ManufacturedFakeFill) Sin() Text {
	return Text{Name: "manufactured-fake-fill", Skill: skills.Absence{}, Description: "An empty scalar fills a slot that expects a meaningful value", Rule: "Keep absence explicit and resolve it before calling.", Suggestion: "Keep absence explicit and resolve it before calling.", Formerly: map[catalog.Engine]Former{catalog.Python: {Sin: "python-invented-default", Detector: "InventedDefaultDetector"}, catalog.CSharp: {Sin: "csharp-invented-default", Detector: "InventedDefaultDetector"}}}
}

// CoalescedLoopSubject finds the loop header replaces a missing collection with an empty one.
type CoalescedLoopSubject struct{}

func (CoalescedLoopSubject) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.CoalescesLoopSubject).Get()
}
func (CoalescedLoopSubject) Sin() Text {
	return Text{Name: "coalesced-loop-subject", Skill: skills.Absence{}, Description: "The loop header replaces a missing collection with an empty one", Rule: "Handle missing input before iteration or require a collection.", Suggestion: "Handle missing input before iteration or require a collection.", Formerly: map[catalog.Engine]Former{catalog.Python: {Sin: "python-coalesced-loop-subject"}, catalog.CSharp: {Sin: "csharp-coalesced-loop-subject"}}}
}

// ConditionalArraySpread finds a spread branch supplies an empty collection as a placeholder.
type ConditionalArraySpread struct{}

func (ConditionalArraySpread) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.IsConditionalSpread).Get()
}
func (ConditionalArraySpread) Sin() Text {
	return Text{Name: "conditional-array-spread", Skill: skills.Absence{}, Description: "A spread branch supplies an empty collection as a placeholder", Rule: "Build the collection from an explicit conditional contribution.", Suggestion: "Build the collection from an explicit conditional contribution.", Formerly: map[catalog.Engine]Former{catalog.Python: {Sin: "python-conditional-spread", Detector: "ConditionalSpreadDetector"}, catalog.CSharp: {Sin: "csharp-conditional-spread", Detector: "ConditionalSpreadDetector"}}}
}
