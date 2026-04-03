<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Neighborhood;

class FindDonutIncidentsCommand extends Command
{
    protected $signature = 'donut:incidents
                            {neighborhood=NB-7A2F : Neighborhood name}
                            {--inner=0.5 : Inner radius in km}
                            {--outer=2.0 : Outer radius in km}
                            {--local=sqlite : Local connection used for neighborhood/incidents}';
    protected $description = 'Find incidents in a donut around a neighborhood centroid, ordered by distance, and print the concatenated code flag.';

    public function handle()
    {
        $name = $this->argument('neighborhood');
        $inner = (float)$this->option('inner');
        $outer = (float)$this->option('outer');
        $localConnection = (string) $this->option('local');

        $neighborhood = Neighborhood::on($localConnection)->where('name', $name)->first();
        if (!$neighborhood) {
            $this->error('Neighborhood not found');
            return 1;
        }

        $this->info('Neighborhood: ' . $neighborhood->name);
        $this->info('Boundary: ' . $neighborhood->boundary);
        
        $incidents = $neighborhood->incidents($inner, $outer)->get();
        $codes = $incidents->map(function ($incident) {
            if (! empty($incident->code)) {
                return $incident->code;
            }

            $metadataCode = data_get($incident->metadata, 'incident.code');

            return is_string($metadataCode) ? $metadataCode : '';
        })->implode('');
        
        $this->info('Matching incidents: ' . $incidents->count());
        $this->info('Flag: ' . $codes);
        return 0;
    }
}
