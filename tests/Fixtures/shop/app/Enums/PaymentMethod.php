<?php

namespace Shop\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case Ideal = 'ideal';
    case PayPal = 'paypal';
}
