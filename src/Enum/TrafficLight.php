<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

use LaSouris\CreditCheck\Sdk\Response\TrafficLight as SdkTrafficLight;

/**
 * The colour EDR assigns to an order or one of its screening steps.
 */
enum TrafficLight: string
{
    case Green = 'Green';
    case Orange = 'Orange';
    case Red = 'Red';

    public static function fromWire(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom($value) : null;
    }

    public function toSdk(): SdkTrafficLight
    {
        return match ($this) {
            self::Green => SdkTrafficLight::Green,
            self::Orange => SdkTrafficLight::Orange,
            self::Red => SdkTrafficLight::Red,
        };
    }

    /**
     * How bad this light is; used to fold several screening steps into one verdict.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Green => 0,
            self::Orange => 1,
            self::Red => 2,
        };
    }
}
