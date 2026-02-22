<?php

declare(strict_types=1);

namespace App\Database;

use App\Database\Schema\TimescalePostgresBuilder;
use Illuminate\Database\PostgresConnection;

class TimescalePostgresConnection extends PostgresConnection
{
    public function getSchemaBuilder(): TimescalePostgresBuilder
    {
        if ($this->schemaGrammar === null) {
            $this->useDefaultSchemaGrammar();
        }

        return new TimescalePostgresBuilder($this);
    }
}
