<?php

declare(strict_types=1);

namespace App\Database\Schema;

use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Database\Schema\PostgresBuilder;

class TimescalePostgresBuilder extends PostgresBuilder
{
    /**
     * Drop all tables from the database.
     *
     * TimescaleDB hypertables cannot be dropped in the same statement as
     * regular tables, so we execute one DROP TABLE statement per table.
     */
    public function dropAllTables(): void
    {
        $grammar = $this->grammar;

        if (! $grammar instanceof PostgresGrammar) {
            return;
        }

        $tables = [];

        $excludedTablesConfig = $this->connection->getConfig('dont_drop');
        $excludedTables = ['spatial_ref_sys'];

        if (is_array($excludedTablesConfig)) {
            $excludedTables = [];

            foreach ($excludedTablesConfig as $table) {
                if (is_string($table) || is_int($table) || is_float($table) || is_bool($table)) {
                    $excludedTables[] = (string) $table;
                }
            }
        }

        foreach ($this->getTables($this->getCurrentSchemaListing()) as $table) {
            if (empty(array_intersect([$table['name'], $table['schema_qualified_name']], $excludedTables))) {
                $tables[] = $table['schema_qualified_name'];
            }
        }

        foreach ($tables as $table) {
            $this->connection->statement($grammar->compileDropAllTables([$table]));
        }
    }
}
