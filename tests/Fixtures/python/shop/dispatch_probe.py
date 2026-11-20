class CourierDispatch:
    def __init__(self, weights: dict[str, int], available: set[str]):
        self.weights = weights
        self.available = available

    def carries(self, address: str) -> bool:
        return address in self.available

    def parcels(self) -> list[str]:
        return sorted(self.weights.keys())

    def load(self, address: str, limit: int) -> int:
        return min(limit, self.weights[address])


# @sin CapabilityProbeHub
class DispatchProbe:
    def __init__(self, courier: object, address: str):
        self.courier = courier
        self.address = address

    def can_accept(self) -> bool:
        if isinstance(self.courier, CourierDispatch):
            return self.courier.carries(self.address)
        return False

    def parcel_list(self) -> list[str]:
        if isinstance(self.courier, CourierDispatch):
            return self.courier.parcels()
        return []

    def load_size(self, vehicle_limit: int) -> int:
        if isinstance(self.courier, CourierDispatch):
            return self.courier.load(self.address, vehicle_limit)
        return 0
