<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\DatabasePdo\Tests\Support;

use Elavora\Api\Framework\Contracts\Insertable;

final class InsertableValue implements Insertable
{
    public function __construct(private readonly string|int|bool|float|null $value) {}

    public function value(): string|int|bool|float|null
    {
        return $this->value;
    }
}
