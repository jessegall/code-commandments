package golang_test

import ()

var flows = map[string]string{
	"go.mod": "module example.com/flows\n",
	"orders/orders.go": `package orders

type Status int

const (
	Draft Status = iota
	Paid
	Shipped
	Refunded
)

type Order struct {
	Status Status
	Lines  []Line
	Note   string
}

type Line struct {
	Quantity int
	Cents    int
}

func (o Order) labelled() string {
	if o.Status == Draft {
		return "draft"
	} else {
		return "placed"
	}
}

func (o Order) dedented() string {
	if o.Status == Draft {
		return "draft"
	}

	return "placed"
}

func (o Order) billed() int {
	total := 0
	for _, line := range o.Lines {
		if line.Quantity > 0 {
			total += line.Cents
			total -= line.Cents / 10
		}
	}

	return total
}

func (o Order) firstPaid() int {
	for _, line := range o.Lines {
		if line.Quantity > 0 {
			total := line.Cents
			return total
		}
	}

	return 0
}

func (o Order) badge() string {
	if o.Status == Draft {
		return "grey"
	} else if o.Status == Paid {
		return "green"
	} else if o.Status == Shipped {
		return "blue"
	} else if o.Status == Refunded {
		return "red"
	}

	return ""
}

func (o Order) mixed() string {
	if o.Status == Draft {
		return "grey"
	} else if o.Note == "" {
		return "blank"
	} else if len(o.Lines) == 0 {
		return "empty"
	} else if o.Status == Paid {
		return "green"
	}

	return ""
}

func (o Order) guarded() string {
	if o.Note == "" {
		return "none"
	}
	if len(o.Lines) == 0 {
		return "empty"
	}

	return o.Note
}
`,
}
