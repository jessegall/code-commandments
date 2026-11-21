package engine

func Recurring(count int, key func(at int) (string, bool), minimum int) [][]int {
	var order []string
	groups := map[string][]int{}
	for at := range count {
		read, ok := key(at)
		if !ok {
			continue
		}
		if _, seen := groups[read]; !seen {
			order = append(order, read)
		}
		groups[read] = append(groups[read], at)
	}
	var recurring [][]int
	for _, read := range order {
		if len(groups[read]) >= minimum {
			recurring = append(recurring, groups[read])
		}
	}

	return recurring
}
