// Two carriers price a parcel with the same steps and different numbers — a base fee, a rate per
// kilo, a surcharge threshold. The numbers are the parameters of one pricing function.

interface Parcel {
    kilos: number
    oversized: boolean
}

export class PostalRates {
    // @sin NearDuplicateFunction
    quote(parcel: Parcel): number {
        let cents = 695 + Math.ceil(parcel.kilos) * 120
        if (parcel.oversized) {
            cents += 450
        }
        if (parcel.kilos > 20) {
            cents = Math.round(cents * 1.15)
        }
        return cents
    }
}

export class CourierRates {
    // @sin NearDuplicateFunction
    quote(parcel: Parcel): number {
        let price = 950 + Math.ceil(parcel.kilos) * 85
        if (parcel.oversized) {
            price += 700
        }
        if (parcel.kilos > 30) {
            price = Math.round(price * 1.1)
        }
        return price
    }
}
