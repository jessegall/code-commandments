package shop

type Celsius float64

type List[T any] []T

type Number interface {
	int | float64
}

type Reader interface{ Read() string }
type Closer interface{ Close() }
type ReadCloser interface {
	Reader
	Closer
}

const Limit int = 3

type Box[T Number] struct{ value T }

type File struct{}

func (File) Read() string { return "" }
func (File) Close()       {}

func Sum[T Number](values []T, lookup map[string]int, handler func(int) error) (total T, err error) {
	for _, each := range values {
		total += each
	}
	return
}

func Convert(value any) (int, bool) {
	var count int = Limit + 1
	number, ok := value.(int)
	return number + count + int(float64(count)), ok
}
