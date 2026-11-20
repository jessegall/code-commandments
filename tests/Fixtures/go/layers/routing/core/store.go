package core

// @sin NamespaceDependency
import "commandments.fixture/shop/layers/routing/view"

func Caption(stops []string) []string {
	printed := make([]string, 0, len(stops)+1)
	printed = append(printed, view.Caption)
	for _, stop := range stops {
		if stop != "" {
			printed = append(printed, stop)
		}
	}
	return printed
}
