<?php

declare(strict_types=1);

namespace LaSouris\CreditCheck\Edr\Enum;

/**
 * How many people live at the applicant's address. EDR buckets the count rather than taking
 * the raw number, so the SDK's plain integer is bucketed here.
 */
enum OccupantCount: string
{
    case One = 'One';
    case Two = 'Two';
    case Three = 'Three';
    case Four = 'Four';
    case MoreAsFour = 'MoreAsFour';

    public static function fromCount(int $occupants): self
    {
        return match (true) {
            $occupants <= 1 => self::One,
            $occupants === 2 => self::Two,
            $occupants === 3 => self::Three,
            $occupants === 4 => self::Four,
            default => self::MoreAsFour,
        };
    }
}
