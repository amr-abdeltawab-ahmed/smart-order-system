<?php

namespace App\Enums;

enum OrderStatus: string implements \JsonSerializable
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
