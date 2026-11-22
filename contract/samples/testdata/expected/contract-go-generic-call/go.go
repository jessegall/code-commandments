package shop

func Larger[T int | float64](a, b T) T {
	if a > b {
		return a
	}
	return b
}

func Use() int {
	return Larger[int](1, 2)
}
