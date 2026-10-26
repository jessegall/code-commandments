package shop

// Entry is one line of the ledger.
type Entry struct {
	Account string
	Cents   int
	Void    bool
}

// Ledger keeps every entry a day posted.
type Ledger struct {
	entries []Entry
	totals  map[string]int
	posted  int
}

// Post adds every entry that stands for money to the day's totals.
func (l *Ledger) Post(entries []Entry) {
	for _, entry := range entries {
		// @sin LoopWrappedInIf
		if !entry.Void && entry.Cents != 0 {
			l.totals[entry.Account] += entry.Cents
			l.posted++
		}
	}
}

// @fixed LoopWrappedInIf

// PostFlat posts the same entries with the work at the loop's own level: the loop says what it skips in its
// first line, and everything after it is the posting.
func (l *Ledger) PostFlat(entries []Entry) {
	for _, entry := range entries {
		if entry.Void || entry.Cents == 0 {
			continue
		}
		l.totals[entry.Account] += entry.Cents
		l.posted++
	}
}

// @righteous LoopWrappedInIf

// FirstVoid is the account of the first void entry worth a note. The body ends by leaving the loop, so the
// condition is picking the one entry it wanted rather than guarding the loop's work.
func (l *Ledger) FirstVoid() string {
	for _, entry := range l.entries {
		if entry.Void {
			l.posted--

			return entry.Account
		}
	}

	return ""
}

// @righteous LoopWrappedInIf

// Settle writes each account's total back as a single entry. The condition binds the total it tests, and Go
// scopes that name to the branch, so inverting it into a guard would widen the name into the loop.
func (l *Ledger) Settle() {
	for _, entry := range l.entries {
		if total, kept := l.totals[entry.Account]; kept && total != 0 {
			l.entries = append(l.entries, Entry{Account: entry.Account, Cents: total})
			delete(l.totals, entry.Account)
		}
	}
}
