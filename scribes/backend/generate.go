package backend

// The backend scribes are held to what the PHP tool's own scribes make of every source PHP's scribe tests read.
// Recording runs those tests again and commits each source with PHP's fix, so the tests hold Go to PHP's whole output;
// it asks PHP's DataHintScribe again for every hints project too.
//go:generate go test -count=1 -run TestTheRecordedScribeCasesAreGeneratedFromTodaysSources|TestDataHintRewritesEachHintsProjectAsThePHPToolDoes -record
