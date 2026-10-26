package shop

// Message is what a till sends on about an order.
type Message struct {
	Kind    string
	Channel string
	Body    string
	Retries int
}

// Router sends each message where its kind says.
type Router struct {
	queues map[string][]Message
	open   bool
}

// Route hands every message to the queue its kind names, and says how many it placed.
func (r *Router) Route(batch []Message) int {
	placed := 0
	if r.open {
		for _, message := range batch {
			switch message.Kind {
			case "receipt":
				// @sin DeepNesting
				if message.Channel != "" {
					r.queues[message.Channel] = append(r.queues[message.Channel], message)
					placed++
				}
			case "refund":
				r.queues["refunds"] = append(r.queues["refunds"], message)
				placed++
			}
		}
	}

	return placed
}

// @righteous DeepNesting

// Retried hands back the messages worth another try. A switch inside a loop is two choices, which a reader
// follows without losing the thread.
func (r *Router) Retried(batch []Message) []Message {
	var again []Message
	for _, message := range batch {
		switch {
		case message.Retries > 3:
			continue
		case message.Kind == "receipt":
			again = append(again, message)
		}
	}

	return again
}

// Open says whether the router will take a batch, and why it will not.
func (r *Router) Open(kind string) (bool, string) {
	// @sin RedundantElse
	if !r.open {
		return false, "the router is closed"
	} else {
		r.queues[kind] = nil

		return true, ""
	}
}
