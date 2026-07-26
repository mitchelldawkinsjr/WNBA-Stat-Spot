<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_score_predictions', function (Blueprint $table) {
            $table->json('feature_snapshot')->nullable()->after('confidence');
            $table->string('model_version', 64)->nullable()->after('feature_snapshot');
        });

        Schema::table('tracked_prop_predictions', function (Blueprint $table) {
            $table->json('feature_snapshot')->nullable()->after('reasoning');
            $table->string('model_version', 64)->nullable()->after('feature_snapshot');
        });

        Schema::create('prediction_model_params', function (Blueprint $table) {
            $table->id();
            $table->string('version', 64)->unique();
            $table->string('status', 16); // champion | challenger | retired
            $table->json('params');
            $table->json('metrics')->nullable();
            $table->unsignedInteger('sample_size')->nullable();
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('prediction_feedback_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_uuid')->unique();
            $table->string('status', 32); // completed | aborted | skipped
            $table->boolean('promoted')->default(false);
            $table->string('champion_version', 64)->nullable();
            $table->string('challenger_version', 64)->nullable();
            $table->unsignedBigInteger('champion_report_id')->nullable();
            $table->unsignedInteger('sample_size')->default(0);
            $table->json('metrics')->nullable();
            $table->json('challenger_params')->nullable();
            $table->json('notes')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('prediction_champion_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_uuid')->unique();
            $table->unsignedBigInteger('feedback_run_id')->nullable();
            $table->string('from_version', 64);
            $table->string('to_version', 64);
            $table->timestamp('promoted_at');
            $table->string('headline');
            $table->text('summary_markdown');
            $table->json('changes');
            $table->json('metrics_before')->nullable();
            $table->json('metrics_after')->nullable();
            $table->json('reasons');
            $table->json('calibration_buckets')->nullable();
            $table->timestamps();

            $table->index('promoted_at');
            $table->foreign('feedback_run_id')
                ->references('id')
                ->on('prediction_feedback_runs')
                ->nullOnDelete();
        });

        $defaultParams = json_encode([
            'adjustments' => [
                'rest_b2b' => 0.9,
                'rest_well' => 1.1,
                'home' => 1.05,
                'opponent_scale' => 0.001,
            ],
            'calibration' => [
                'shrinkage' => 0.0,
            ],
            'gates' => [
                'min_confidence' => 0.6,
                'min_ev' => 0.05,
                'by_stat' => (object) [],
            ],
        ]);

        DB::table('prediction_model_params')->insert([
            'version' => 'bootstrap.1',
            'status' => 'champion',
            'params' => $defaultParams,
            'metrics' => null,
            'sample_size' => null,
            'window_start' => null,
            'window_end' => null,
            'promoted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_champion_reports');
        Schema::dropIfExists('prediction_feedback_runs');
        Schema::dropIfExists('prediction_model_params');

        Schema::table('tracked_prop_predictions', function (Blueprint $table) {
            $table->dropColumn(['feature_snapshot', 'model_version']);
        });

        Schema::table('game_score_predictions', function (Blueprint $table) {
            $table->dropColumn(['feature_snapshot', 'model_version']);
        });
    }
};
