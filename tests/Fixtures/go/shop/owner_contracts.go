package shop

// @righteous LoneImplementerInterface
type receiptRenderer interface{ ReceiptTitle() string }

type shortReceipt struct{ title string }

func (r shortReceipt) ReceiptTitle() string { return r.title }

type longReceipt struct{ region string }

func (r longReceipt) ReceiptTitle() string { return "Receipt for " + r.region }

type receiptJob struct {
	// @righteous UnreadCollaborator
	// @fixed UnreadCollaborator
	renderer receiptRenderer
}

func NewReceiptJob(
	// @righteous ConcreteCrossModuleDependency
	// @fixed ConcreteCrossModuleDependency
	renderer receiptRenderer,
) *receiptJob {
	return &receiptJob{renderer: renderer}
}

// @righteous MiddleMan
// @fixed MiddleMan
func (j *receiptJob) Caption() string { return "Customer copy: " + j.currentTitle() }

// @righteous UnusedPrivateMember
func (j *receiptJob) currentTitle() string { return j.renderer.ReceiptTitle() }

type receiptHistory struct{ tags map[string]string }

func (h receiptHistory) Remember(key string, tag string) { h.tags[key] = tag }

// @righteous HandRolledRegistry
func (h receiptHistory) Tag(key string) (string, bool) {
	tag, found := h.tags[key]
	return tag, found
}

// @fixed TrainWreck
// @righteous TrainWreck
func checkoutReceiptCaption(job *receiptJob) string { return job.Caption() }

type forwardingStage int

const (
	forwardingWaiting forwardingStage = iota
	forwardingSent
)

type forwardingReceipt struct{ stage forwardingStage }

// @righteous GuardBesideTransition
// @fixed GuardBesideTransition
func (r *forwardingReceipt) Dispatch() {
	if r.stage != forwardingWaiting {
		panic("already dispatched")
	}
	r.stage = forwardingSent
}

func forwardingStageCaption(stage forwardingStage) string {
	switch stage {
	case forwardingWaiting:
		return "waiting"
	case forwardingSent:
		return "sent"
	// @righteous MatchDefaultReturnsNull
	// @fixed MatchDefaultReturnsNull
	default:
		panic("unexpected stage")
	}
}
