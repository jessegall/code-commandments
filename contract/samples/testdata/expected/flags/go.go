package flags

func Sum(values ...int) int {
	total := 0
	for i := 0; i < len(values); i++ {
		total += values[i]
	}
	return total
}

func Twice(values []int) int {
	return Sum(values...) * 2
}
