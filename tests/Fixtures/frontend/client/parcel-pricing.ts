// The FIX for two carriers pricing a parcel with the same steps: the steps live in ONE function,
// and the numbers that differed are a tariff each carrier passes in.

interface Parcel {
    kilos: number
    oversized: boolean
}

interface Tariff {
    base: number
    perKilo: number
    oversizeFee: number
    heavyFrom: number
    heavyFactor: number
}

// @fixed NearDuplicateFunction
export function priceParcel(parcel: Parcel, tariff: Tariff): number {
    const weighed = tariff.base + Math.ceil(parcel.kilos) * tariff.perKilo
    const sized = parcel.oversized ? weighed + tariff.oversizeFee : weighed
    return parcel.kilos > tariff.heavyFrom ? Math.round(sized * tariff.heavyFactor) : sized
}

export class RegisteredPost {
    // @fixed NearDuplicateFunction
    quote(parcel: Parcel): number {
        return priceParcel(parcel, { base: 695, perKilo: 120, oversizeFee: 450, heavyFrom: 20, heavyFactor: 1.15 })
    }
}

export class ExpressCourier {
    // @fixed NearDuplicateFunction
    quote(parcel: Parcel): number {
        return priceParcel(parcel, { base: 950, perKilo: 85, oversizeFee: 700, heavyFrom: 30, heavyFactor: 1.1 })
    }
}
