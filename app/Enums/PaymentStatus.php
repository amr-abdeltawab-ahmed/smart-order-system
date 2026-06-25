<?php

namespace App\Enums;

enum PaymentStatus: string implements \JsonSerializable
{
    case Pending    = 'pending';
    case Successful = 'successful';
    case Failed     = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
