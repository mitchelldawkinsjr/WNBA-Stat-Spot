<?php

namespace App\Services\WNBA\Data;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Statement;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class ImporterService
{
    private const CHUNK_SIZE = 1000;
    private const MEMORY_LIMIT = 512; // MB

    /**
     * Import data from a CSV file with memory-efficient streaming
     */
    public function importFromCsv(string $filePath, string $table, array $columnMapping, callable $transformCallback = null): array
    {
        try {
            if (!Storage::exists($filePath)) {
                throw new \RuntimeException("File not found: {$filePath}");
            }

            $csv = Reader::createFromPath(Storage::path($filePath), 'r');
            $csv->setHeaderOffset(0);

            $totalRecords = count($csv);
            $processedRecords = 0;
            $errors = [];
            $startTime = microtime(true);

            // Process in chunks to manage memory
            foreach ($this->getChunkedRecords($csv) as $chunk) {
                try {
                    DB::beginTransaction();

                    $records = $chunk->map(function ($record) use ($columnMapping, $transformCallback) {
                        $data = [];
                        foreach ($columnMapping as $csvColumn => $dbColumn) {
                            $data[$dbColumn] = $record[$csvColumn] ?? null;
                        }

                        return $transformCallback ? $transformCallback($data) : $data;
                    })->filter();

                    if ($records->isNotEmpty()) {
                        DB::table($table)->insert($records->toArray());
                    }

                    DB::commit();
                    $processedRecords += $records->count();

                    // Log progress
                    $this->logProgress($processedRecords, $totalRecords, $startTime);

                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors[] = [
                        'chunk_start' => $processedRecords,
                        'error' => $e->getMessage()
                    ];
                    Log::error('Error processing chunk', [
                        'error' => $e->getMessage(),
                        'chunk_start' => $processedRecords
                    ]);
                }

                // Check memory usage
                $this->checkMemoryUsage();
            }

            return [
                'total_records' => $totalRecords,
                'processed_records' => $processedRecords,
                'errors' => $errors,
                'execution_time' => round(microtime(true) - $startTime, 2)
            ];

        } catch (\Exception $e) {
            Log::error('CSV import failed', [
                'error' => $e->getMessage(),
                'file' => $filePath
            ]);
            throw $e;
        }
    }

    /**
     * Get records from CSV in chunks
     */
    private function getChunkedRecords(Reader $csv): LazyCollection
    {
        return LazyCollection::make(function () use ($csv) {
            $offset = 0;
            $stmt = Statement::create();

            while ($offset < count($csv)) {
                $stmt->offset($offset)->limit(self::CHUNK_SIZE);
                $records = collect($stmt->process($csv));
                yield $records;
                $offset += self::CHUNK_SIZE;
            }
        });
    }

    /**
     * Log import progress
     */
    private function logProgress(int $processed, int $total, float $startTime): void
    {
        $percentage = round(($processed / $total) * 100, 2);
        $elapsedTime = round(microtime(true) - $startTime, 2);
        $recordsPerSecond = round($processed / $elapsedTime, 2);

        Log::info("Import progress: {$percentage}% ({$processed}/{$total})", [
            'records_per_second' => $recordsPerSecond,
            'elapsed_time' => $elapsedTime
        ]);
    }

    /**
     * Check memory usage and log warning if approaching limit
     */
    private function checkMemoryUsage(): void
    {
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // Convert to MB
        $memoryLimit = self::MEMORY_LIMIT;

        if ($memoryUsage > ($memoryLimit * 0.8)) {
            Log::warning('High memory usage during import', [
                'memory_usage_mb' => round($memoryUsage, 2),
                'memory_limit_mb' => $memoryLimit
            ]);
        }
    }

    /**
     * Import multiple CSV files in parallel
     */
    public function importMultipleFiles(array $imports): array
    {
        $results = [];
        foreach ($imports as $import) {
            try {
                $results[$import['file']] = $this->importFromCsv(
                    $import['file'],
                    $import['table'],
                    $import['columns'],
                    $import['transform'] ?? null
                );
            } catch (\Exception $e) {
                $results[$import['file']] = [
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }
        return $results;
    }

    /**
     * Validate CSV file structure
     */
    public function validateCsvStructure(string $filePath, array $requiredColumns): bool
    {
        try {
            $csv = Reader::createFromPath(Storage::path($filePath), 'r');
            $csv->setHeaderOffset(0);
            $headers = $csv->getHeader();

            $missingColumns = array_diff($requiredColumns, $headers);
            if (!empty($missingColumns)) {
                throw new \RuntimeException(
                    'Missing required columns: ' . implode(', ', $missingColumns)
                );
            }

            return true;
        } catch (\Exception $e) {
            Log::error('CSV validation failed', [
                'error' => $e->getMessage(),
                'file' => $filePath
            ]);
            throw $e;
        }
    }
}
