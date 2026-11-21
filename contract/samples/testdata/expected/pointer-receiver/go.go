package shapes

type Shape interface {
	Area() int
}

type Square struct {
	side int
}

func (s *Square) Area() int {
	return s.side * s.side
}
