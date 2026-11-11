package hooks

import (
	"os"
	"testing"
)

const everyJudgingChosen = `{"judge_edits": true, "judge_workers": true, "judge_worker_edits": true, "judge_hand_ins": true, "judge_merges": true}`

func TestMain(m *testing.M) {
	os.Setenv(journalSettings, everyJudgingChosen)

	os.Exit(m.Run())
}
