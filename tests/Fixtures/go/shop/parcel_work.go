package shop

type ParcelWork interface {
	Weigh()
	Wrap()
	Label()
	Stage()
	Insure()
}

// @sin DivergentTwin
func stageParcel(work ParcelWork) {
	work.Weigh()
	work.Wrap()
	work.Label()
	work.Stage()
}

// @fixed DivergentTwin
func routeInsuredParcel(work ParcelWork) {
	stageInsuredParcel(work)
}
