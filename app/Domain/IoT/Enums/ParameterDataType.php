<?php

declare(strict_types=1);

namespace App\Domain\IoT\Enums;

enum ParameterDataType: string
{
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case String = 'string';

    public function label(): string
    {
        return match ($this) {
            self::Integer => 'Integer',
            self::Decimal => 'Decimal',
            self::Boolean => 'Boolean',
            self::String => 'String',
        };
    }
}
