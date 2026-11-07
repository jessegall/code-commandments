package hooks

import "testing"

func TestOnlyACeilingDeclineIsAnsweredWithTheOneFileRules(t *testing.T) {
	for declined, narrows := range map[string]bool{
		"1451 files differ from the tree, past the 1000 this service reads for one judging (…)": true,
		string(noTreeHeld):     false,
		string(noTreeComing):   false,
		string(checkoutGone):   false,
		string(filesNotOnDisk): false,
	} {
		if said := pastWhatOneSpliceCarries(declined); said != narrows {
			t.Errorf("a judging declined with %q is narrowed: %v, where it must be %v. A smaller answer is right "+
				"only when the tree is HELD and cannot carry the whole question; with no tree at all we wait for "+
				"one rather than reading files ourselves", declined, said, narrows)
		}
	}
}
