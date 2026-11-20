package efcore

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

var terminalQueries = []string{"ToList", "ToListAsync", "ToArray", "ToArrayAsync", "First", "FirstAsync", "FirstOrDefault", "FirstOrDefaultAsync", "Single", "SingleAsync", "SingleOrDefault", "SingleOrDefaultAsync", "Count", "CountAsync", "Any", "AnyAsync", "Find", "FindAsync"}

func ExecutesQuery(call engine.Match) bool {
	if !slices.Contains(terminalQueries, call.CalleeName()) {
		return false
	}
	if strings.Contains(call.TargetSymbol(), "Microsoft.EntityFrameworkCore.") {
		return true
	}
	for receiver := call.Receiver(); receiver.Exists(); receiver = receiver.Receiver() {
		if typed := receiver.Typed(); typed != nil && strings.Contains(typed.Text, "Microsoft.EntityFrameworkCore.DbSet<") {
			return true
		}
	}
	return false
}
