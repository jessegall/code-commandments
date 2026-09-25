package prose

import "testing"

func TestHistoryIsToldFromARuntimeCondition(t *testing.T) {
	for text, narrates := range map[string]bool{
		"Renamed from DealerProfileInformationUpdated.":                              true,
		"This was extracted into its own class.":                                     true,
		"Returns early if empty. It used to be a dictionary.":                        true,
		"true if a brand was extracted; otherwise false.":                            false,
		"Gets where this candidate now lives in the register.":                       false,
		"Matches drafts. Used to fire the fallback rule so dealers see an estimate.": false,
		"Mirrors the switch the registration used to hold.":                          true,
		"This helper now lives in the Pricing module.":                               true,
		"Reports whether the job was extracted into the queue.":                      false,
	} {
		if NarratesHistory(text) != narrates {
			t.Errorf("%q narrates history: %v", text, !narrates)
		}
	}
}

func TestAStrawmanIsToldFromARuntimeCondition(t *testing.T) {
	for text, defends := range map[string]bool{
		"The stream id is deterministic, not random.":                                          true,
		"There is no green anywhere in this palette, and that is not an oversight.":            true,
		"This is deliberate, not a mistake.":                                                   true,
		"Visibility is deliberately not re-checked.":                                           true,
		"The website is not here either: it is on the record.":                                 true,
		"Operation capabilities are NOT declared here.":                                        true,
		"Published beside every place so a reader can never mistake a town for a doorstep.":    false,
		"The context records it so a reader does not mistake it for a verified principal.":     false,
		"The instant stays null, so nothing later can mistake our clock for the provider's.":   false,
		"Far from the other, so a test that read the wrong one could not pass by coincidence.": false,
		"Omits it from the list when the requested channel is not in this set.":                false,
		"An entry whose town is not here is not drawn at all.":                                 false,
		"Names that do not resolve — a typo in a secret must not take the process down.":       false,
		"Because Scrutor is not present in this solution, each rule is registered by hand.":    false,
		"The raw condition string is not in this source's condition map.":                      false,
	} {
		if DefendsAgainstStrawman(text) != defends {
			t.Errorf("%q defends against a strawman: %v", text, !defends)
		}
	}
}

func TestWordsAreStemmedContentWords(t *testing.T) {
	if got := Words("Saves the order's totalPrice settled"); len(got) != 5 || got[0] != "sav" || got[2] != "total" || got[4] != "settl" {
		t.Errorf("the words are %v", got)
	}
	if Paragraphs([]string{"one", "two", "", ">>> x", "", "three"}, func(line string) bool { return line[0] != '>' }) != 2 {
		t.Error("two prose paragraphs")
	}
}
