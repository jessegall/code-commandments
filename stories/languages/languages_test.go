//go:build story

package languages

import (
	"testing"

	"github.com/jessegall/code-commandments/story"
)

func served(t *testing.T) (*story.Story, *story.Agent) {
	t.Helper()

	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	return s, s.Agent("Ada")
}

func TestAPythonEditIsJudgedWithTheTypesThePythonServiceHolds(t *testing.T) {
	s, ada := served(t)

	ada.Edits(typedElseAfterRaise, "orders.py")

	s.Says(ada.Hears("orders.py"), "orders.py")
}

func TestAPHPEditIsJudged(t *testing.T) {
	s, ada := served(t)

	ada.Edits(phpDeepNesting, "Checkout.php")

	s.Says(ada.Hears("Checkout.php"), "Checkout.php")
}

func TestATypeScriptEditIsJudged(t *testing.T) {
	s, ada := served(t)

	ada.Edits(tsCoalesced, "order.ts")

	s.Says(ada.Hears("order.ts"), "order.ts")
}

func TestAVueEditIsJudgedInItsTemplateAndItsScript(t *testing.T) {
	s, ada := served(t)

	ada.Edits(vueChain, "State.vue")

	s.Says(ada.Hears("State.vue"), "State.vue")
}

func TestACSharpEditIsJudgedThroughTheCSharpService(t *testing.T) {
	s, ada := served(t)

	ada.Edits(csharpNull, "Orders.cs")

	s.Says(ada.Hears("Orders.cs"), "Orders.cs")
}

func TestACSharpEditSaysItWasReadPerCheckoutNotPerFile(t *testing.T) {
	s, ada := served(t)

	ada.Edits(csharpOther, "Customers.cs")

	s.Says(ada.Hears("Customers.cs"), "per checkout")
}

func TestAGoEditIsJudgedAsItIsEdited(t *testing.T) {
	s, ada := served(t)

	ada.Edits(goFlow, "refund.go")

	s.Says(ada.Hears("refund.go"), "refund.go")
}

func TestAGoSiblingsSinIsNotTheEditedFilesMark(t *testing.T) {
	s, ada := served(t)

	s.Writes(goSibling, "notify.go")
	ada.Edits(goClean, "total.go")
	ada.StaysUntold("notify.go")
}

func TestAGoPackageIsReadWholeToJudgeOneOfItsFiles(t *testing.T) {
	s, ada := served(t)

	s.Writes(goFlow, "refund.go")
	ada.Edits(goDuplicate, "refund_again.go")

	s.Says(ada.Hears("refund_again.go"), "refund.go")
}

func TestAWholeRunOverEveryLanguageGroupsFindingsBySkill(t *testing.T) {
	s, _ := served(t)

	s.Writes(typedElseAfterRaise, "orders.py")
	s.Writes(goFlow, "refund.go")

	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Says(judged, "orders.py", "refund.go")
}

func TestARuleThatNeedsAPackageFiresOnlyWhereTheProjectHasIt(t *testing.T) {
	s, ada := served(t)

	ada.Edits(phpDeepNesting, "Controller.php")
	ada.StaysUntold("laravel")

	s.Never(s.ReadsTheLanes(), "laravel")
}

func TestDuplicatedFunctionsAcrossTwoTypeScriptFilesAreFound(t *testing.T) {
	s, ada := served(t)

	s.Writes(tsFormatOne, "format.ts")
	ada.Edits(tsFormatTwo, "label.ts")

	s.Says(ada.Hears("label.ts"), "format.ts")
}

func TestAFrontendTypeThatMirrorsAServerTypeIsFlaggedFromTheServersFacts(t *testing.T) {
	s, ada := served(t)

	ada.Edits(tsMirror, "OrderData.ts")

	s.Says(ada.Hears("OrderData.ts"), "OrderData")
}

func TestACSharpEditInAWorkersWorktreeIsReadFromThatWorktree(t *testing.T) {
	s, _ := served(t)
	grace := s.Worker("Grace")

	grace.Edits(csharpNull, "Orders.cs")

	s.Never(grace.Hears("Orders.cs"), "nothing of this edit was judged")
}

func TestAGoEditInAWorkersWorktreeIsJudgedAgainstHerGoPackage(t *testing.T) {
	s, _ := served(t)
	grace := s.Worker("Grace")

	grace.Edits(goFlow, "refund.go")
	grace.Edits(goDuplicate, "refund_again.go")

	s.Says(grace.Hears("refund_again.go"), "refund.go")
}

func TestAFileOfALanguageNoOneJudgesIsNotAnyonesToJudge(t *testing.T) {
	s, ada := served(t)

	ada.Edits(rubyFile, "refund.rb")
	ada.StaysUntold("refund.rb")

	s.Says(s.Asks("commandments", "why", "refund.rb"), "Nothing has judged")
}

func TestAProjectRuleThatFollowsAChainOfParentsIsNotAskedAboutOneFile(t *testing.T) {
	s, ada := served(t)

	s.Writes(csharpNull, "Base.cs")
	ada.Edits(csharpOther, "Child.cs")
	ada.Hears("Child.cs")

	s.CostIs("tree_reads_started", 0)
}

func TestEveryEngineARuleJudgesIsAskedAboutAnEditedFile(t *testing.T) {
	s, ada := served(t)

	ada.Edits(phpDeepNesting, "A.php")
	ada.Edits(typedElseAfterRaise, "b.py")

	s.Says(ada.Hears("b.py"), "b.py")
}

func TestAMergeRangeWithSinsInTwoLanguagesNamesBoth(t *testing.T) {
	s, _ := served(t)
	grace := s.Worker("Grace")

	grace.Edits(typedElseAfterRaise, "orders.py")
	grace.Edits(goFlow, "refund.go")
	grace.HandsIn()
	s.Merges(grace)

	s.Says(grace.Hears("orders.py"), "refund.go")
}

func TestASinInATypeScriptFileIsToldAtTheLineTheEditorShows(t *testing.T) {
	s, ada := served(t)

	ada.Edits(tsAfterImports, "late.ts")

	s.Says(ada.Hears("late.ts"), "late.ts:41")
}
