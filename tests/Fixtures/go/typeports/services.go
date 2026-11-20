package typeports

type StockGraphService struct{ Caption string }

func (s *StockGraphService) Read() string {
	return s.Caption + "stock"
}

type ParcelGraphService struct{ Caption string }

func (s *ParcelGraphService) Read() string {
	return s.Caption + "parcel"
}

type TillGraphService struct{ Caption string }

func (s *TillGraphService) Read() string {
	return s.Caption + "till"
}
