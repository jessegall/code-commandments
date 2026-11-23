package prose

import "testing"

func TestHistoryIsToldFromARuntimeCondition(t *testing.T) {
	for text, narrates := range map[string]bool{
		"Renamed from DealerProfileInformationUpdated.":                                          true,
		"This was extracted into its own class.":                                                 true,
		"Returns early if empty. It used to be a dictionary.":                                    true,
		"This used to support games, hubs and forums, but the only caller searches users.":       true,
		"The resolver adapts each pipe at build time, so no pre-wrapping happens here anymore.":  true,
		"Previously attempted with a controller, but removed as it was not the right direction.": true,
		"Resumes the previously started run where it stopped.":                                   false,
		"This must be refactored into the helper function below.":                                false,
		"true if a brand was extracted; otherwise false.":                                        false,
		"Gets where this candidate now lives in the register.":                                   false,
		"Matches drafts. Used to fire the fallback rule so dealers see an estimate.":             false,
		"Mirrors the switch the registration used to hold.":                                      true,
		"This helper now lives in the Pricing module.":                                           true,
		"Reports whether the job was extracted into the queue.":                                  false,
		"The agent holds work until it is retired.":                                              false,
		"Ends attempts the host took but no longer runs.":                                        false,
		"A column that no longer reads fails the read.":                                          false,
		"This method no longer reads the legacy format.":                                         true,
		"Similarity matching no longer offers up a claimed candidate.":                           true,
		"The executor no longer does instanceof unwraps.":                                        true,
		"Mark a reader unhealthy when it no longer does its checks.":                             false,
		"// no longer an array":                                                                  true,
		"No longer a singleton: each request builds its own.":                                    true,
		"Drops a session that is no longer a member of the pool.":                                false,
		"// code below is no longer relevant.":                                                   true,
		"Skips a token that is no longer needed by any reader.":                                  false,
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
		"Not using a strict comparison is on purpose as the token might be null.":              false,
		"we do not delete logs here on purpose (you can run these queries to delete all data)": false,
		"We never retry here on purpose.":                                                      true,
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
		"The index is deliberately not unique. A replayed run walks the workflow again.":       false,
		"Deliberately not invented — a fabricated testimonial is a lie about a business.":      false,
		"Deletes rows from log_action that are not in this temporary table.":                   false,
		"The tag is deliberately not in the key: a board renders it as a div or a button.":     false,
		"A cold cache may be warm next request. It is deliberately not refreshed here.":        false,
		"Deliberately not cached (the rates change by the minute).":                            false,
		"Properties not listed here are left untouched, even if they contain entities.":        false,
		"The order's total is not stored here.":                                                true,
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

func TestABareVerbIsTheVerbAndNothingElse(t *testing.T) {
	for _, name := range []string{"holds", "Holds", "spins", "ships", "binds", "shows"} {
		if !IsBareVerb(name) {
			t.Errorf("%s is the verb alone, and reads as nothing but an action", name)
		}
	}

	for _, name := range []string{"requiresApproval", "RequiresApproval", "receivesVisitors", "shipsToAuthenticator", "countsTowardStrikes", "namesANaturalPerson"} {
		if IsBareVerb(name) {
			t.Errorf("%s says what of, so it is not the verb alone", name)
		}
	}

	for _, name := range []string{"isShown", "approval", ""} {
		if IsBareVerb(name) {
			t.Errorf("%s is no third-person verb, so it cannot be a bare one", name)
		}
	}
}

func TestAStateVerbNamesABareStateWhereNoQuestionDoes(t *testing.T) {
	for name, bare := range map[string]bool{
		"divides":    true,
		"bursts":     true,
		"proposes":   true,
		"narrows":    true,
		"negates":    true,
		"isDivided":  false,
		"canPropose": false,
	} {
		if IsBareStateName(name) != bare {
			t.Errorf("IsBareStateName(%q) = %v, want %v", name, !bare, bare)
		}
	}
}
