<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SubmitRepoCommand extends Command
{
    protected $signature = 'submit:repo
                            {repo_url : Public GitHub repository URL}
                            {cv : Path to CV PDF file}
                            {--token= : Bearer token for challenge API}
                            {--endpoint=https://illuminate.bitech.com.sa/api/challenge/submit-repo : Submission endpoint}
                            {--dry-run : Validate inputs and print request plan without sending}';

    protected $description = 'Submit repository URL and CV PDF to the challenge API';

    public function handle(): int
    {
        $repoUrl = (string) $this->argument('repo_url');
        $cvInputPath = (string) $this->argument('cv');
        $endpoint = (string) $this->option('endpoint');
        $dryRun = (bool) $this->option('dry-run');

        $token = (string) ($this->option('token') ?: env('CHALLENGE_TOKEN', ''));

        if (! filter_var($repoUrl, FILTER_VALIDATE_URL)) {
            $this->error('Invalid repo_url. Provide a full URL.');

            return self::FAILURE;
        }

        $cvPath = $this->resolvePath($cvInputPath);
        if (! is_file($cvPath)) {
            $this->error('CV file not found: ' . $cvPath);

            return self::FAILURE;
        }

        if (strtolower(pathinfo($cvPath, PATHINFO_EXTENSION)) !== 'pdf') {
            $this->error('CV must be a PDF file.');

            return self::FAILURE;
        }

        if (! filter_var($endpoint, FILTER_VALIDATE_URL)) {
            $this->error('Invalid endpoint URL.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Dry run mode: no request sent.');
            $this->line('Endpoint: ' . $endpoint);
            $this->line('repo_url: ' . $repoUrl);
            $this->line('cv: ' . $cvPath);

            return self::SUCCESS;
        }

        if ($token === '') {
            $this->error('Token is required. Use --token=... or set CHALLENGE_TOKEN in .env');

            return self::FAILURE;
        }

        $response = Http::withToken($token)
            ->attach('cv', file_get_contents($cvPath), basename($cvPath))
            ->post($endpoint, [
                'repo_url' => $repoUrl,
            ]);

        if ($response->successful()) {
            $this->info('Repository submitted successfully.');
            $this->line($response->body());

            return self::SUCCESS;
        }

        $this->error('Submission failed with HTTP ' . $response->status());
        $this->line($response->body());

        return self::FAILURE;
    }

    private function resolvePath(string $path): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path));

        if ($normalized === '') {
            return $normalized;
        }

        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $normalized) || str_starts_with($normalized, DIRECTORY_SEPARATOR)) {
            return $normalized;
        }

        return base_path($normalized);
    }
}
