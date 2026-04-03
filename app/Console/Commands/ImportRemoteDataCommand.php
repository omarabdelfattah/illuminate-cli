<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Neighborhood;
use App\Models\Incident;
use App\Models\ProjectConfig;

class ImportRemoteDataCommand extends Command
{
    protected $signature = 'import:remote-data {--local=sqlite : Local destination connection name (sqlite or mysql)}';
    protected $description = 'Import remote PostgreSQL data into a local database connection';

    public function handle()
    {
        $remote = DB::connection('pgsql');
        $localConnection = (string) $this->option('local');

        if (! in_array($localConnection, ['sqlite', 'mysql'], true)) {
            $this->error('Invalid --local value. Use sqlite or mysql.');

            return self::FAILURE;
        }

        // Import neighborhoods
        $neighborhoods = $remote->table('neighborhoods')->get()->map(function ($row) {
            return $this->normalizeNeighborhoodRow((array) $row);
        });
        foreach ($neighborhoods as $row) {
            Neighborhood::on($localConnection)->updateOrCreate(['id' => $row['id']], $row);
        }

        // Import incidents
        $incidents = $remote->table('incidents')->get()->map(function ($row) {
            return $this->normalizeIncidentRow((array) $row);
        });
        foreach ($incidents as $row) {
            Incident::on($localConnection)->updateOrCreate(['id' => $row['id']], $row);
        }

        // Import config
        $configs = $remote->table('config')->get()->map(function ($row) {
            return $this->normalizeConfigRow((array) $row);
        });
        foreach ($configs as $row) {
            ProjectConfig::on($localConnection)->updateOrCreate(['key' => $row['key']], $row);
        }

        $this->info('Local destination connection: ' . $localConnection);
        $this->info('Imported neighborhoods: ' . count($neighborhoods));
        $this->info('Imported incidents: ' . count($incidents));
        $this->info('Imported config rows: ' . count($configs));

        return self::SUCCESS;
    }

    private function normalizeNeighborhoodRow(array $row): array
    {
        if (array_key_exists('properties', $row)) {
            $row['properties'] = $this->normalizeJsonValue($row['properties']);
        }

        return $row;
    }

    private function normalizeIncidentRow(array $row): array
    {
        foreach (['metadata', 'tags', 'reports'] as $jsonField) {
            if (array_key_exists($jsonField, $row)) {
                $row[$jsonField] = $this->normalizeJsonValue($row[$jsonField]);
            }
        }

        return $row;
    }

    private function normalizeConfigRow(array $row): array
    {
        if (array_key_exists('value', $row)) {
            $row['value'] = $this->normalizeJsonValue($row['value']);
        }

        return $row;
    }

    private function normalizeJsonValue(mixed $value): mixed
    {
        if ($value === null || is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $decodedJson = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decodedJson;
        }

        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            return $this->parsePgArrayLiteral($trimmed);
        }

        return $value;
    }

    private function parsePgArrayLiteral(string $literal): array
    {
        $content = substr($literal, 1, -1);
        if ($content === '') {
            return [];
        }

        $items = str_getcsv($content, ',', '"', '\\');

        return array_map(function (?string $item): mixed {
            if ($item === null || strtoupper($item) === 'NULL') {
                return null;
            }

            $unescapedItem = str_replace(['\\\\', '\\"'], ['\\', '"'], $item);
            $decodedJson = json_decode($unescapedItem, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decodedJson;
            }

            return $unescapedItem;
        }, $items);
    }
}
