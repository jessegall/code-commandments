package shop

// @sin LoneImplementerInterface
type CounterTicketWriter interface {
	WriteCounterTicket(sequence int, lanes []string) error
}

type CounterTerminal struct {
	Output func([]byte) error
}

func (terminal CounterTerminal) WriteCounterTicket(sequence int, lanes []string) error {
	header := []byte{byte(sequence), byte(len(lanes))}
	return terminal.Output(header)
}

type CounterSequence struct {
	count int
	ready chan<- int
}

func (queue *CounterSequence) Next() int {
	queue.count++
	return queue.count
}

func (queue *CounterSequence) Publish(count int) {
	queue.ready <- count
}

// @sin MiddleMan
type CounterTicketCounter struct {
	queue *CounterSequence
}

func (counter CounterTicketCounter) Next() int {
	return counter.queue.Next()
}

func (counter CounterTicketCounter) Publish(count int) {
	counter.queue.Publish(count)
}

// @sin HandRolledRegistry
type CounterTicketBoard struct {
	tickets map[int][]int
	desk    string
}

func (board *CounterTicketBoard) Display(desk chan<- string, numbers []int) {
	for range numbers {
		desk <- board.desk
	}
}

func (board *CounterTicketBoard) Allocate(room int, tickets []int) {
	board.tickets[room] = append([]int(nil), tickets...)
}

func (board *CounterTicketBoard) Maximum(room int) int {
	if tickets, listed := board.tickets[room]; !listed || len(tickets) == 0 {
		panic("no tickets at the counter")
	}
	farthest := 0
	for _, number := range board.tickets[room] {
		farthest = max(farthest, number)
	}
	return farthest
}

type CounterTicketWindow struct {
	// @sin UnreadCollaborator
	terminal *CounterTerminal
	closed   []int
}

func (window *CounterTicketWindow) Close(number int) {
	window.closed = append(window.closed, number)
}

// @sin UnusedPrivateMember
func (window CounterTicketWindow) receipt(send chan<- int) {
	for _, number := range window.closed {
		send <- number
	}
}

func counterNotice(
	// @sin ArrayBagParameter
	revisions map[string][]int,
	stream chan<- int,
) {
	for _, ids := range revisions {
		for _, called := range ids {
			stream <- called
		}
	}
}

func announceCounterChanges(stream chan<- int) {
	counterNotice(map[string][]int{"finished": {2, 4}, "late": {9}}, stream)
	counterNotice(map[string][]int{"finished": {3}, "late": {17, 18}}, stream)
}

type CounterTicketState int

const (
	ticketWaiting CounterTicketState = iota
	ticketCalled
	ticketRemoved
)

func counterTicketDisplay(state CounterTicketState, tickets map[int]string, number int) map[int]string {
	switch state {
	case ticketWaiting:
		return map[int]string{}
	case ticketCalled:
		return tickets
	case ticketRemoved:
		delete(tickets, number)
		return tickets
	// @sin MatchDefaultReturnsNull
	default:
		return nil
	}
}
