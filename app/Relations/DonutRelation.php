<?php

namespace App\Relations;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Models\Incident;

class DonutRelation extends Relation
{
    protected float $innerRadius;
    protected float $outerRadius;

    public function __construct(Model $parent, float $innerRadius, float $outerRadius)
    {
        $this->parent = $parent;
        $this->innerRadius = $innerRadius;
        $this->outerRadius = $outerRadius;

        $query = Incident::query();
        if ($parent->getConnectionName() !== null) {
            $query = Incident::on($parent->getConnectionName());
        }

        parent::__construct($query, $parent);
    }

    public function addConstraints(): void
    {
        // Intentionally empty: filtering is done in-memory in get()
        // to support both SQLite and MySQL without DB-level geometry functions.
    }

    public function addEagerConstraints(array $models): void
    {
        // Intentionally empty: this relation is resolved from the parent model context.
    }

    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, collect());
        }

        return $models;
    }

    public function match(array $models, Collection $results, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $results);
        }

        return $models;
    }

    protected function getCentroid(): array
    {
        $boundary = $this->parent->boundary;
        $decoded = is_string($boundary) ? json_decode($boundary, true) : $boundary;

        $points = [];
        if (is_array($decoded)) {
            $points = $this->collectPointsFromArray($decoded);
        }

        // Only fall back to string parsing if JSON decoding failed entirely.
        if (empty($points) && json_last_error() !== JSON_ERROR_NONE) {
            $points = $this->collectPointsFromString((string) $boundary);
        }

        if (empty($points)) {
            return ['lat' => 0.0, 'lng' => 0.0];
        }

        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');

        return [
            'lat' => (min($lats) + max($lats)) / 2,
            'lng' => (min($lngs) + max($lngs)) / 2,
        ];
    }

    private function collectPointsFromArray(array $value): array
    {
        $points = [];

        $walker = function ($node) use (&$walker, &$points): void {
            if (! is_array($node)) {
                return;
            }

            if (count($node) === 2 && is_numeric($node[0]) && is_numeric($node[1])) {
                $points[] = [
                    'lat' => (float) $node[1], // index 1 = lat
                    'lng' => (float) $node[0], // index 0 = lng
                ];
                return;
            }

            foreach ($node as $child) {
                $walker($child);
            }
        };

        $walker(array_key_exists('coordinates', $value) ? $value['coordinates'] : $value);

        return $points;
    }

    private function collectPointsFromString(string $value): array
    {
        preg_match_all('/\(?\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*\)?/', $value, $matches, PREG_SET_ORDER);

        $points = [];
        foreach ($matches as $match) {
            $points[] = [
                'lat' => (float) $match[1],
                'lng' => (float) $match[2],
            ];
        }

        return $points;
    }

    private function parseIncidentLocation(string $location): ?array
    {
        if (! preg_match('/\(?\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*\)?/', $location, $matches)) {
            return null;
        }

        return [
            'lat' => (float) $matches[1],
            'lng' => (float) $matches[2],
        ];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    public function getResults(): Collection
    {
        return $this->get();
    }

    public function get($columns = ['*'])
    {
        $centroid = $this->getCentroid();

        return $this->query->get()
            ->map(function (Incident $incident) use ($centroid) {
                $point = $this->parseIncidentLocation((string) $incident->location);
                if ($point === null) {
                    return null;
                }

                $classification = (string) data_get($incident->metadata, 'incident.classification', '');
                if (! preg_match('/^alpha-\d+$/', $classification)) {
                    return null;
                }

                $incident->distance = $this->haversineKm(
                    $centroid['lat'],
                    $centroid['lng'],
                    $point['lat'],
                    $point['lng']
                );

                return $incident;
            })
            ->filter(function ($incident) {
                return $incident !== null
                    && $incident->distance >= $this->innerRadius
                    && $incident->distance <= $this->outerRadius;
            })
            ->sortBy('distance')
            ->values();
    }
}