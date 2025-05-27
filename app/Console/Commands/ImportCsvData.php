<?php

namespace App\Console\Commands;

use App\Jobs\ImportCsvData;
use App\Services\WNBA\Data\ImporterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportCsvDataCommand extends Command
{
    protected $signature = 'wnba:import-csv
                            {file : Path to the CSV file}
                            {table : Target database table}
                            {--columns=* : Column mappings in format csv_column:db_column}
                            {--validate-only : Only validate the CSV structure}
                            {--queue : Process the import in the background}';

    protected $description = 'Import data from a CSV file into the database';

    public function handle(ImporterService $importer): int
    {
        $filePath = $this->argument('file');
        $table = $this->argument('table');
        $columnMappings = $this->parseColumnMappings();
        $validateOnly = $this->option('validate-only');
        $useQueue = $this->option('queue');

        try {
            // Validate file exists
            if (!Storage::exists($filePath)) {
                $this->error("File not found: {$filePath}");
                return 1;
            }

            // Validate CSV structure
            $this->info('Validating CSV structure...');
            $requiredColumns = array_keys($columnMappings);

            if (!$importer->validateCsvStructure($filePath, $requiredColumns)) {
                $this->error('CSV validation failed');
                return 1;
            }

            if ($validateOnly) {
                $this->info('CSV validation successful');
                return 0;
            }

            // Process import
            if ($useQueue) {
                $this->info('Dispatching import job to queue...');
                ImportCsvData::dispatch($filePath, $table, $columnMappings);
                $this->info('Import job dispatched successfully');
            } else {
                $this->info('Starting import process...');
                $result = $importer->importFromCsv($filePath, $table, $columnMappings);

                $this->info('Import completed successfully');
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Total Records', $result['total_records']],
                        ['Processed Records', $result['processed_records']],
                        ['Errors', count($result['errors'])],
                        ['Execution Time', $result['execution_time'] . ' seconds']
                    ]
                );

                if (!empty($result['errors'])) {
                    $this->warn('Import completed with errors:');
                    foreach ($result['errors'] as $error) {
                        $this->line("- Chunk {$error['chunk_start']}: {$error['error']}");
                    }
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function parseColumnMappings(): array
    {
        $mappings = [];
        $columns = $this->option('columns');

        foreach ($columns as $mapping) {
            if (!str_contains($mapping, ':')) {
                $this->error("Invalid column mapping format: {$mapping}");
                continue;
            }

            [$csvColumn, $dbColumn] = explode(':', $mapping);
            $mappings[$csvColumn] = $dbColumn;
        }

        if (empty($mappings)) {
            $this->error('No column mappings provided');
            throw new \RuntimeException('No column mappings provided');
        }

        return $mappings;
    }
}
