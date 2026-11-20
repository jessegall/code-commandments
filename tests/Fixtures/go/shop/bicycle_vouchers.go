package shop

// @sin LoneImplementerInterface
type BicycleVoucherDesk interface {
	ClaimVoucher(voucher string) bool
	CancelClaim(voucher string)
	OpenSlots() int
	ClaimCount() int
}

type BicycleVoucherStand struct {
	claims map[string]bool
	slots  int
}

func (stand *BicycleVoucherStand) ClaimVoucher(voucher string) bool {
	if stand.slots <= 0 {
		return false
	}
	stand.claims[voucher] = true
	stand.slots--
	return true
}

func (stand *BicycleVoucherStand) CancelClaim(voucher string) {
	delete(stand.claims, voucher)
	stand.slots++
}

func (stand BicycleVoucherStand) OpenSlots() int { return stand.slots }

func (stand BicycleVoucherStand) ClaimCount() int { return len(stand.claims) }

// @sin MiddleMan
type BicycleVoucherForwarder struct {
	stand *BicycleVoucherStand
}

func (forwarder BicycleVoucherForwarder) ClaimVoucher(voucher string) bool {
	return forwarder.stand.ClaimVoucher(voucher)
}

func (forwarder BicycleVoucherForwarder) CancelClaim(voucher string) {
	forwarder.stand.CancelClaim(voucher)
}

func (forwarder BicycleVoucherForwarder) OpenSlots() int {
	return forwarder.stand.OpenSlots()
}

// @sin HandRolledRegistry
type BicycleVoucherArchive struct {
	issued   map[string]int64
	redeemed int64
}

func (archive *BicycleVoucherArchive) Issue(code string, cents int64) {
	archive.issued[code] = cents
}

func (archive *BicycleVoucherArchive) Redeem(code string) {
	if _, issued := archive.issued[code]; !issued {
		panic("unissued voucher")
	}
	archive.redeemed += archive.issued[code]
	delete(archive.issued, code)
}

func (archive BicycleVoucherArchive) VoucherTotal() int64 { return archive.redeemed }

type BicycleVoucherReport struct {
	issuedToday uint
	redeemed    uint
	totals      []int64
	// @sin UnreadCollaborator
	archive *BicycleVoucherArchive
}

func (report *BicycleVoucherReport) AddBatch(issued, redeemed uint, cents int64) {
	report.issuedToday += issued
	report.redeemed += redeemed
	report.totals = append(report.totals, cents)
}

// @sin UnusedPrivateMember
func (report BicycleVoucherReport) stranded() uint {
	if report.issuedToday < report.redeemed {
		return 0
	}
	return report.issuedToday - report.redeemed
}

type BicycleVoucherStatus string

const (
	voucherHeld      BicycleVoucherStatus = "held"
	voucherCancelled BicycleVoucherStatus = "cancelled"
)

func bicycleVoucherNotices(state BicycleVoucherStatus, codes []string, output chan<- string) []string {
	switch state {
	case voucherHeld:
		published := make([]string, 0, len(codes))
		for _, code := range codes {
			output <- code
			published = append(published, code)
		}
		return published
	case voucherCancelled:
		return append([]string{"voided"}, codes...)
	// @sin MatchDefaultReturnsNull
	default:
		return nil
	}
}
