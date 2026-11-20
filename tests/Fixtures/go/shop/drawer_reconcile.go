package shop

type DrawerWork interface {
	OpenTill()
	CountNotes()
	MatchReceipts()
	CloseDrawer()
	RecordDifference()
}

// @sin DivergentTwin
func balanceTill(work DrawerWork) {
	work.OpenTill()
	work.CountNotes()
	work.MatchReceipts()
	work.CloseDrawer()
}

// @sin DivergentTwin
func auditDrawer(work DrawerWork) {
	work.OpenTill()
	work.CountNotes()
	work.MatchReceipts()
	work.CloseDrawer()
	work.RecordDifference()
}
