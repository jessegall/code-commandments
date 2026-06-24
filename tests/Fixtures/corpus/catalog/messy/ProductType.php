<?php

namespace App\Catalog;

class ProductType
{
    const PHYSICAL = 'physical';
    const DIGITAL = 'digital';
    const SUBSCRIPTION = 'subscription';

    public static function isShippable($type)
    {
        if ($type == 'physical') {
            return true;
        } elseif ($type == 'digital') {
            return false;
        } elseif ($type == 'subscription') {
            return false;
        }

        return false;
    }

    public static function tracksStock($type)
    {
        return $type == 'physical' ? true : false;
    }

    public static function label($type)
    {
        if ($type == 'physical') {
            return 'Physical good';
        }
        if ($type == 'digital') {
            return 'Digital download';
        }
        if ($type == 'subscription') {
            return 'Subscription';
        }

        return 'Unknown';
    }

    public static function classify($gatewayName)
    {
        if (in_array($gatewayName, ['Stripe', 'Paypal'])) {
            return 'digital';
        }

        return 'physical';
    }
}
