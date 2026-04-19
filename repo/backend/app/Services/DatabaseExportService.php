<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generates a deterministic SQL-like dump of the active database connection.
 *
 * The artifact is driver-agnostic: it reads every table via the query builder
 * and serializes rows as portable INSERT statements. The produced file starts
 * with a fixed header line so callers (e.g. backup jobs) can validate type
 * before encryption/storage.
 */
final class DatabaseExportService
{
    public const HEADER_MAGIC = '-- CampusLearn SQL Export v1';

    /**
     * @return array{tables:int, rows:int, bytes:int}
     */
    public function export(string $destPath): array
    {
        $dir = dirname($destPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $handle = fopen($destPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open export destination: {$destPath}");
        }

        $connection = DB::connection();
        $driver     = $connection->getDriverName();
        $tables     = $this->listTables($connection, $driver);

        fwrite($handle, self::HEADER_MAGIC . "\n");
        fwrite($handle, '-- generated_at: ' . now()->toIso8601String() . "\n");
        fwrite($handle, '-- driver: ' . $driver . "\n");
        fwrite($handle, '-- tables: ' . count($tables) . "\n\n");

        $rowTotal = 0;
        foreach ($tables as $table) {
            $rows = $connection->table($table)->get();
            fwrite($handle, "-- table: {$table} (rows=" . count($rows) . ")\n");
            foreach ($rows as $row) {
                $rowTotal++;
                $rowArr  = (array) $row;
                $columns = array_keys($rowArr);
                $values  = array_map(fn ($v) => $this->quote($v, $connection), array_values($rowArr));
                fwrite(
                    $handle,
                    'INSERT INTO ' . $this->escapeIdent($table) . ' ('
                    . implode(', ', array_map(fn ($c) => $this->escapeIdent((string) $c), $columns))
                    . ') VALUES (' . implode(', ', $values) . ");\n"
                );
            }
            fwrite($handle, "\n");
        }

        fclose($handle);
        $bytes = (int) filesize($destPath);

        if ($bytes <= 0) {
            throw new RuntimeException('Database export produced empty artifact.');
        }

        return ['tables' => count($tables), 'rows' => $rowTotal, 'bytes' => $bytes];
    }

    /**
     * Confirm a file was produced by this service.
     */
    public function validateArtifact(string $path): bool
    {
        if (! is_file($path) || filesize($path) <= 0) {
            return false;
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $first = fgets($handle);
        fclose($handle);
        return is_string($first) && str_starts_with($first, self::HEADER_MAGIC);
    }

    /**
     * @return list<string>
     */
    private function listTables(Connection $connection, string $driver): array
    {
        try {
            if ($driver === 'mysql') {
                $rows = $connection->select('SHOW TABLES');
                return array_values(array_map(static function ($row) {
                    $arr = (array) $row;
                    return (string) reset($arr);
                }, $rows));
            }
            if ($driver === 'sqlite') {
                $rows = $connection->select(
                    "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
                );
                return array_map(static fn ($r) => (string) $r->name, $rows);
            }
            if ($driver === 'pgsql') {
                $rows = $connection->select(
                    "SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename"
                );
                return array_map(static fn ($r) => (string) $r->tablename, $rows);
            }
        } catch (\Throwable) {
            return [];
        }
        return [];
    }

    private function quote(mixed $value, Connection $connection): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }
        $pdo = $connection->getPdo();
        return $pdo->quote((string) $value);
    }

    private function escapeIdent(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
