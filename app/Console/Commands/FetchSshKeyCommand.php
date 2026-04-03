<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchSshKeyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:ssh-key {--token=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch SSH private key and database credentials for the challenge';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $token = $this->option('token');
        if (!$token) {
            $this->error('Token is required. Use --token=YOUR_TOKEN');
            return 1;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get('https://illuminate.bitech.com.sa/api/challenge/ssh-key');

        if ($response->successful()) {
            $this->info('Fetched credentials:');
            $this->line($response->body());
        } else {
            $this->error('Failed to fetch credentials.');
            $this->line($response->body());
        }
        return 0;
    }
}
