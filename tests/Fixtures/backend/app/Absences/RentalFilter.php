<?php

namespace Shop\Absences;

use Illuminate\Foundation\Http\FormRequest;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Rentals\RentalStatus;

/**
 * The rentals a desk clerk asks to see, by the status picked in the list's filter.
 */
final class RentalFilter extends FormRequest
{
    #[Sinful(CoalescedPastDefault::class)]
    public function status(): RentalStatus
    {
        $picked = $this->enum('status', RentalStatus::class) ?? RentalStatus::Out;

        return $picked === RentalStatus::Lost ? RentalStatus::Out : $picked;
    }

    #[Fixed(CoalescedPastDefault::class)]
    public function pickedStatus(): RentalStatus
    {
        return $this->enum('status', RentalStatus::class, RentalStatus::Out);
    }
}
