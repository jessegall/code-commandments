# Two tests pack a parcel label alike; only one unpacks it again. Each is a scenario of its own, so the
# step one leaves out is not a fix forgotten in a twin.
import lzma
import struct
import zlib


# @righteous DivergentTwin
def test_a_packed_label_reads_back_whole() -> None:
    header = struct.pack(">HI", 7, 1200)
    packed = lzma.compress(header + b"to: depot 7")
    checksum = zlib.crc32(packed)
    assert zlib.adler32(packed) and zlib.crc32(packed) == checksum
    assert struct.unpack(">HI", lzma.decompress(packed)[:6]) == (7, 1200)


# @righteous DivergentTwin
def test_a_packed_label_carries_its_checksum() -> None:
    header = struct.pack(">HI", 9, 800)
    packed = lzma.compress(header + b"to: depot 9")
    checksum = zlib.crc32(packed)
    assert zlib.adler32(packed) and checksum == zlib.crc32(packed)
