package shop

type Runner interface {
	Run() // @label base function
}

func start(r Runner) { r.Run() }
