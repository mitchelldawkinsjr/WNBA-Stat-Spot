<?php

namespace App\Console\Commands;

use App\Services\WnbaDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestWnbaDataService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-wnba-data-service';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the WnbaDataService functionality and robustness';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing WnbaDataService...');

        $service = new WnbaDataService;

        // Test 1: Test helper methods with mock data
        $this->info('1. Testing helper methods...');
        $this->testHelperMethods($service);

        // Test 2: Test parsing with missing columns
        $this->info('2. Testing CSV parsing with missing columns...');
        $this->testParsingWithMissingColumns($service);

        // Test 3: Test actual data download (if available)
        $this->info('3. Testing data download...');
        $this->testDataDownload($service);

        $this->info('All tests completed!');
    }

    private function testHelperMethods($service)
    {
        // Use reflection to test private methods
        $reflection = new \ReflectionClass($service);
        $getOptionalValue = $reflection->getMethod('getOptionalValue');
        $getOptionalValue->setAccessible(true);

        $getOptionalBool = $reflection->getMethod('getOptionalBool');
        $getOptionalBool->setAccessible(true);

        // Test getOptionalValue
        $testData = ['existing_key' => 'test_value'];

        $result1 = $getOptionalValue->invoke($service, $testData, 'existing_key');
        $result2 = $getOptionalValue->invoke($service, $testData, 'missing_key');
        $result3 = $getOptionalValue->invoke($service, $testData, 'missing_key', 'default_value');

        $this->line('   ✓ getOptionalValue with existing key: '.($result1 === 'test_value' ? 'PASS' : 'FAIL'));
        $this->line('   ✓ getOptionalValue with missing key: '.($result2 === null ? 'PASS' : 'FAIL'));
        $this->line('   ✓ getOptionalValue with default: '.($result3 === 'default_value' ? 'PASS' : 'FAIL'));

        // Test getOptionalBool
        $boolTestData = [
            'true_string' => 'TRUE',
            'false_string' => 'FALSE',
            'one_string' => '1',
            'zero_string' => '0',
            'true_bool' => true,
            'false_bool' => false,
        ];

        $boolResult1 = $getOptionalBool->invoke($service, $boolTestData, 'true_string');
        $boolResult2 = $getOptionalBool->invoke($service, $boolTestData, 'false_string');
        $boolResult3 = $getOptionalBool->invoke($service, $boolTestData, 'missing_key');
        $boolResult4 = $getOptionalBool->invoke($service, $boolTestData, 'one_string');

        $this->line("   ✓ getOptionalBool with 'TRUE': ".($boolResult1 === true ? 'PASS' : 'FAIL'));
        $this->line("   ✓ getOptionalBool with 'FALSE': ".($boolResult2 === false ? 'PASS' : 'FAIL'));
        $this->line('   ✓ getOptionalBool with missing key: '.($boolResult3 === false ? 'PASS' : 'FAIL'));
        $this->line("   ✓ getOptionalBool with '1': ".($boolResult4 === true ? 'PASS' : 'FAIL'));
    }

    private function testParsingWithMissingColumns($service)
    {
        // Create a test CSV with missing columns
        $testCsv = "game_id,season,team_name,points\n401654321,2026,Las Vegas Aces,85\n401654322,2026,Seattle Storm,92";

        // Save test CSV
        $testPath = 'test/missing_columns.csv';
        Storage::put($testPath, $testCsv);

        try {
            $records = $service->parseBoxScoreData($testPath);

            if (! empty($records)) {
                $firstRecord = $records[0];
                $hasRequiredFields = isset($firstRecord['game_id']) && isset($firstRecord['season']);
                $hasMissingFields = ! isset($firstRecord['athlete_display_name']) || $firstRecord['athlete_display_name'] === null;

                $this->line('   ✓ Parsing with missing columns: '.($hasRequiredFields && $hasMissingFields ? 'PASS' : 'FAIL'));
                $this->line('   ✓ Records parsed: '.count($records));
                $this->line('   ✓ Sample record game_id: '.($firstRecord['game_id'] ?? 'null'));
                $this->line('   ✓ Sample record missing field (athlete_display_name): '.($firstRecord['athlete_display_name'] ?? 'null'));
            } else {
                $this->line('   ✗ No records parsed - FAIL');
            }
        } catch (\Exception $e) {
            $this->line('   ✗ Parsing failed with error: '.$e->getMessage());
        } finally {
            // Clean up test file
            Storage::delete($testPath);
        }
    }

    private function testDataDownload($service)
    {
        try {
            $this->line('   Testing team schedule data download...');
            $path = $service->downloadTeamScheduleData();

            if (Storage::exists($path)) {
                $this->line('   ✓ Download successful: '.$path);

                // Test parsing the downloaded data
                $records = $service->parseTeamScheduleData($path);
                $this->line('   ✓ Parsed records: '.count($records));

                if (! empty($records)) {
                    $firstRecord = $records[0];
                    $this->line('   ✓ Sample game_id: '.($firstRecord['game_id'] ?? 'null'));
                    $this->line('   ✓ Sample home_team_name: '.($firstRecord['home_team_name'] ?? 'null'));
                }
            } else {
                $this->line('   ✗ Download failed - file not found');
            }
        } catch (\Exception $e) {
            $this->line('   ✗ Download test failed: '.$e->getMessage());
            $this->line('   (This might be expected if network is unavailable)');
        }
    }
}
