# Printing labels for the parcels that still need one — a loop over a loop, the inner body wrapped.


def print_labels(shipments, printer) -> None:
    for shipment in shipments:
        for parcel in shipment.parcels:
            # @sin LoopWrappedInIf
            if parcel.label is None:
                parcel.label = printer.render(shipment.address, parcel.weight)
                printer.print(parcel.label)
