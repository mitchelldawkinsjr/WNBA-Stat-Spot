<?php

namespace App\Services\WNBA\Predictions;

use App\Models\PredictionModelParam;
use Illuminate\Support\Facades\Cache;

class PredictionModelParamStore
{
    private const CACHE_KEY = 'prediction_model_champion_params';

    private const CACHE_TTL = 300;

    /**
     * @return array{
     *     version: string,
     *     adjustments: array{rest_b2b: float, rest_well: float, home: float, opponent_scale: float},
     *     calibration: array{shrinkage: float},
     *     gates: array{min_confidence: float, min_ev: float, by_stat: array<string, array{min_confidence?: float, min_ev?: float}>}
     * }
     */
    public function champion(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $row = PredictionModelParam::query()->champion()->orderByDesc('id')->first();

            if ($row === null) {
                return $this->bootstrapDefaults();
            }

            return $this->normalize($row->version, $row->params ?? []);
        });
    }

    public function championVersion(): string
    {
        return $this->champion()['version'];
    }

    /**
     * @return array{rest_b2b: float, rest_well: float, home: float, opponent_scale: float}
     */
    public function adjustments(): array
    {
        return $this->champion()['adjustments'];
    }

    /**
     * @return array{shrinkage: float}
     */
    public function calibration(): array
    {
        return $this->champion()['calibration'];
    }

    /**
     * @return array{min_confidence: float, min_ev: float, by_stat: array<string, array{min_confidence?: float, min_ev?: float}>}
     */
    public function gates(?string $statType = null): array
    {
        $gates = $this->champion()['gates'];
        if ($statType !== null && isset($gates['by_stat'][$statType])) {
            $override = $gates['by_stat'][$statType];

            return [
                'min_confidence' => (float) ($override['min_confidence'] ?? $gates['min_confidence']),
                'min_ev' => (float) ($override['min_ev'] ?? $gates['min_ev']),
                'by_stat' => $gates['by_stat'],
            ];
        }

        return $gates;
    }

    public function applyShrinkage(float $probability): float
    {
        $shrinkage = (float) ($this->calibration()['shrinkage'] ?? 0.0);
        $shrinkage = max(0.0, min(0.5, $shrinkage));

        return 0.5 + ((1.0 - $shrinkage) * ($probability - 0.5));
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{
     *     version: string,
     *     adjustments: array{rest_b2b: float, rest_well: float, home: float, opponent_scale: float},
     *     calibration: array{shrinkage: float},
     *     gates: array{min_confidence: float, min_ev: float, by_stat: array<string, array{min_confidence?: float, min_ev?: float}>}
     * }
     */
    public function normalize(string $version, array $params): array
    {
        $defaults = $this->defaultParams();

        $adjustments = array_merge($defaults['adjustments'], $params['adjustments'] ?? []);
        $calibration = array_merge($defaults['calibration'], $params['calibration'] ?? []);
        $gates = array_merge($defaults['gates'], $params['gates'] ?? []);
        $gates['by_stat'] = is_array($gates['by_stat'] ?? null) ? $gates['by_stat'] : [];

        return [
            'version' => $version,
            'adjustments' => [
                'rest_b2b' => (float) $adjustments['rest_b2b'],
                'rest_well' => (float) $adjustments['rest_well'],
                'home' => (float) $adjustments['home'],
                'opponent_scale' => (float) $adjustments['opponent_scale'],
            ],
            'calibration' => [
                'shrinkage' => (float) $calibration['shrinkage'],
            ],
            'gates' => [
                'min_confidence' => (float) $gates['min_confidence'],
                'min_ev' => (float) $gates['min_ev'],
                'by_stat' => $gates['by_stat'],
            ],
        ];
    }

    /**
     * @return array{
     *     adjustments: array{rest_b2b: float, rest_well: float, home: float, opponent_scale: float},
     *     calibration: array{shrinkage: float},
     *     gates: array{min_confidence: float, min_ev: float, by_stat: array<string, mixed>}
     * }
     */
    public function defaultParams(): array
    {
        /** @var array{adjustments: array, calibration: array, gates: array} $defaults */
        $defaults = config('wnba.predictions.defaults');

        return [
            'adjustments' => [
                'rest_b2b' => (float) $defaults['adjustments']['rest_b2b'],
                'rest_well' => (float) $defaults['adjustments']['rest_well'],
                'home' => (float) $defaults['adjustments']['home'],
                'opponent_scale' => (float) $defaults['adjustments']['opponent_scale'],
            ],
            'calibration' => [
                'shrinkage' => (float) $defaults['calibration']['shrinkage'],
            ],
            'gates' => [
                'min_confidence' => (float) $defaults['gates']['min_confidence'],
                'min_ev' => (float) $defaults['gates']['min_ev'],
                'by_stat' => is_array($defaults['gates']['by_stat'] ?? null) ? $defaults['gates']['by_stat'] : [],
            ],
        ];
    }

    /**
     * @return array{
     *     version: string,
     *     adjustments: array{rest_b2b: float, rest_well: float, home: float, opponent_scale: float},
     *     calibration: array{shrinkage: float},
     *     gates: array{min_confidence: float, min_ev: float, by_stat: array<string, array{min_confidence?: float, min_ev?: float}>}
     * }
     */
    private function bootstrapDefaults(): array
    {
        $params = $this->defaultParams();

        PredictionModelParam::query()->create([
            'version' => 'bootstrap.1',
            'status' => 'champion',
            'params' => $params,
            'promoted_at' => now(),
        ]);

        return $this->normalize('bootstrap.1', $params);
    }
}
