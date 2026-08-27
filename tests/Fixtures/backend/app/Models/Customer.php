<?php

namespace Shop\Models;

use Illuminate\Database\Eloquent\Model;
use JesseGall\CodeCommandments\Sins\Backend\FeatureEnvy;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\MassUpdateAtCallSite;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ModelMutationAtCallSite;
use JesseGall\CodeCommandments\Sins\Backend\ManufacturedFakeFill;
use JesseGall\CodeCommandments\Testing\Fixed;

class Customer extends Model
{
    protected $fillable = ['name', 'email'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Where every call site's poking-and-saving went: the transition named once, on the model that
     * owns the columns it writes.
     */
    #[Fixed(ModelMutationAtCallSite::class)]
    #[Fixed(FeatureEnvy::class)]
    public function suspend(string $reason): void
    {
        $this->suspended = true;
        $this->suspended_reason = $reason;
        $this->save();
    }

    #[Fixed(MassUpdateAtCallSite::class)]
    public function markVerified(string $at): void
    {
        $this->verified = true;
        $this->verified_at = $at;
        $this->save();
    }

    #[Fixed(ManufacturedFakeFill::class)]
    public function markImported(): void
    {
        $this->imported = true;
        $this->save();
    }
}
