# Generating a product thumbnail, with every possible failure — even a KeyboardInterrupt — dropped.


def make_thumbnail(image, size) -> None:
    try:
        image.resize(size).save(image.thumbnail_path)
    # @sin SwallowedException
    except:
        pass
