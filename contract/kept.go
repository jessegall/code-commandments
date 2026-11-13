package contract

import "fmt"

// Kept reads file lines a cache kept between runs, as the reader of the stream that wrote them read them: decoded,
// linked and checked under the stream's header.
type Kept struct{ reader Reader }

func KeptUnder(header Header) *Kept {
	return &Kept{Reader{header: &header}}
}

func (k *Kept) File(raw []byte) (*File, error) {
	line, err := decode(raw)
	if err != nil {
		return nil, err
	}
	if line.File == nil {
		return nil, fmt.Errorf("a kept line holds no file")
	}
	if err := k.reader.acceptFile(line.File); err != nil {
		return nil, err
	}

	return line.File, nil
}
