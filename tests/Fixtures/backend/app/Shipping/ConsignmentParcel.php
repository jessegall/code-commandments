<?php

namespace Shop\Shipping;

use Illuminate\Database\Eloquent\Model;

/**
 * One parcel of a consignment, with its weight.
 */
class ConsignmentParcel extends Model
{
    protected $fillable = ['consignment_id', 'reference', 'grams'];
}
