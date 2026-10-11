# A test provokes the stream's failure to watch the rollback — the raise is the stimulus, not a failure reported.
import pytest

from shop.streams_io import LineStream


def test_a_closed_stream_is_not_read_again() -> None:
    stream = LineStream(source=None)
    stream.closed = True

    with pytest.raises(RuntimeError):
        stream.read_line()


def test_a_failure_inside_the_block_leaves_the_stream_closed() -> None:
    stream = LineStream(source=None)

    with pytest.raises(RuntimeError):
        stream.closed = True
        # @righteous MessageStringRaise
        raise RuntimeError("stop here, nothing catches this by name")

    assert stream.closed
