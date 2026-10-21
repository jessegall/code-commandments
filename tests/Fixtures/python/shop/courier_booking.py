# Books a courier over its HTTP API from inside a coroutine, with the blocking client. The request holds the
# event loop for as long as the courier takes to answer, and a booking round of ten parcels is then ten
# requests long rather than one, however many of them could have been in flight together.

import requests

COURIER = "https://courier.test/book"


class CourierBooking:
    def __init__(self, depot: str) -> None:
        self.depot = depot

    async def book(self, parcel: str) -> bool:
        # @sin BlockedAwait
        answered = requests.post(COURIER, json={"parcel": parcel, "depot": self.depot})

        return answered.status_code == 201

    async def book_all(self, parcels: list[str]) -> int:
        booked = 0
        for parcel in parcels:
            if await self.book(parcel):
                booked += 1

        return booked
