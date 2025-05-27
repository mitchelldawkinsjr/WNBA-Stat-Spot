<?php

namespace App\Jobs;

use App\Services\WNBA\Data\ImporterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportCsvData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $filePath;
    private string $table;
    private array $columnMapping;
    private ?callable $transformCallback;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $filePath,
        string $table,
        array $columnMapping,
        ?callable $transformCallback = null
    ) {
        $this->filePath = $filePath;
        $this->table = $table;
        $this->columnMapping = $columnMapping;
        $this->transformCallback = $transformCallback;
    }

    /**
     * Execute the job.
     */
    public function handle(ImporterService $importer): void
    {
        try {
            Log::info('Starting CSV import job', [
                'file' => $this->filePath,
                'table' => $this->table
            ]);

            $result = $importer->importFromCsv(
                $this->filePath,
                $this->table,
                $this->columnMapping,
                $this->transformCallback
            );

            Log::info('CSV import job completed', [
                'file' => $this->filePath,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('CSV import job failed', [
                'file' => $this->filePath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CSV import job failed', [
            'file' => $this->filePath,
            'error' => $exception->getMessage()
        ]);
    }
}
