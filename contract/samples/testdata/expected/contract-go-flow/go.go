package shop

func scan(rows [][]int, ready chan int, quit chan struct{}) int {
	total := 0
outer:
	for _, row := range rows {
		for _, cell := range row {
			if cell < 0 {
				continue outer
			}
			if cell > 100 {
				break outer
			}
			total += cell
		}
	}
	if next := total + 1; next > 10 {
		goto done
	}
	defer close(quit)
	select {
	case value := <-ready:
		total += value
	default:
	}
	go func() { ready <- 1 }()
	ready <- 2
	;
done:
	return total
}
