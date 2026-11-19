<?php

namespace Shop\Fulfillment;

use Illuminate\Database\Eloquent\Model;
use JesseGall\CodeCommandments\Sins\Backend\ArrayBagParameter;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Picker;

/**
 * One picker's run through a lane. `admit` takes its columns as a string-keyed array, so the fields a run
 * starts with have no type; `admitted` takes the admission as a value its callers construct.
 */
final class PickRun extends Model
{
    #[Sinful(ArrayBagParameter::class)]
    public static function admit(Picker $picker, array $admission): self
    {
        return static::query()->create([...$admission, 'picker_id' => $picker->id]);
    }

    #[Fixed(ArrayBagParameter::class)]
    public static function admitted(Picker $picker, PickAdmission $admission): self
    {
        return static::query()->create([
            'picker_id' => $picker->id,
            'lane' => $admission->lane,
            'priority' => $admission->priority,
        ]);
    }
}

/**
 * What a pick run is admitted with: the lane it walks and how urgent it is.
 */
#[Fixed(ArrayBagParameter::class)]
final readonly class PickAdmission
{
    public function __construct(
        public string $lane,
        public int $priority,
    ) {}
}
