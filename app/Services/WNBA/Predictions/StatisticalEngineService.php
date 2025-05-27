<?php

namespace App\Services\WNBA\Predictions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * StatisticalEngineService
 *
 * Core statistical analysis and prediction engine for WNBA player statistics.
 * Handles probability calculations, distribution analysis, and Monte Carlo simulations.
 */
class StatisticalEngineService
{
    /**
     * Update Bayesian probability with new evidence
     *
     * @param float $prior Prior probability (0-1)
     * @param float $likelihood Likelihood of evidence given hypothesis
     * @param float $evidence Probability of evidence
     * @return float Updated posterior probability
     */
    public function updateBayesianProbability(float $prior, float $likelihood, float $evidence): float
    {
        if ($evidence <= 0) {
            return $prior;
        }

        $posterior = ($likelihood * $prior) / $evidence;
        return max(0, min(1, $posterior));
    }

    /**
     * Calculate Poisson probability for exact value
     *
     * @param float $lambda Mean rate of occurrence
     * @param int $k Number of occurrences
     * @return float Probability of exactly k occurrences
     */
    public function calculatePoissonProbability(float $lambda, int $k): float
    {
        if ($lambda <= 0 || $k < 0) {
            return 0;
        }

        return (pow($lambda, $k) * exp(-$lambda)) / $this->factorial($k);
    }

    /**
     * Calculate Poisson probability for over/under scenarios
     *
     * @param float $lambda Mean rate of occurrence
     * @param float $threshold Threshold value for over/under
     * @return float Probability of exceeding threshold
     */
    public function calculatePoissonOverProbability(float $lambda, float $threshold): float
    {
        if ($lambda <= 0) return 0.5;

        $underProbability = 0;
        $maxK = max(50, ceil($threshold + 5 * sqrt($lambda))); // Reasonable upper bound

        for ($k = 0; $k <= floor($threshold); $k++) {
            $underProbability += $this->calculatePoissonProbability($lambda, $k);
        }

        return 1 - $underProbability;
    }

    /**
     * Calculate normal distribution probability density
     *
     * @param float $mean Mean of the distribution
     * @param float $stdDev Standard deviation
     * @param float $value Value to calculate probability for
     * @return float Probability density at value
     */
    public function calculateNormalProbability(float $mean, float $stdDev, float $value): float
    {
        if ($stdDev <= 0) {
            return $value == $mean ? 1.0 : 0.0;
        }

        $coefficient = 1 / ($stdDev * sqrt(2 * M_PI));
        $exponent = -0.5 * pow(($value - $mean) / $stdDev, 2);

        return $coefficient * exp($exponent);
    }

    /**
     * Calculate normal distribution CDF (cumulative distribution function)
     *
     * @param float $mean Mean of the distribution
     * @param float $stdDev Standard deviation
     * @param float $value Value to calculate CDF for
     * @return float Cumulative probability up to value
     */
    public function calculateNormalCDF(float $mean, float $stdDev, float $value): float
    {
        if ($stdDev <= 0) {
            return $value >= $mean ? 1.0 : 0.0;
        }

        $zScore = ($value - $mean) / $stdDev;
        return $this->normalCDF($zScore);
    }

    /**
     * Run Monte Carlo simulation for a player's stat prediction
     *
     * @param int $playerId Player ID
     * @param int $gameId Game ID
     * @param string $statType Type of statistic to simulate
     * @param float $lineValue Line value for over/under
     * @param int $iterations Number of simulation iterations
     * @return array Simulation results including probabilities and confidence intervals
     * @throws \RuntimeException If no historical data is available
     */
    public function runMonteCarloSimulation(
        int $playerId,
        int $gameId,
        string $statType,
        float $lineValue,
        int $iterations = 10000
    ): array {
        try {
            // Get historical data
            $historicalData = $this->getHistoricalData($playerId, $statType);
            if (empty($historicalData)) {
                throw new \RuntimeException("No historical data available for player {$playerId}");
            }

            // Calculate distribution parameters
            $distribution = $this->determineDistributionType($historicalData);
            $params = $this->calculateDistributionParameters($historicalData, $distribution);

            // Run simulation
            $results = [];
            $overCount = 0;
            $totalValue = 0;

            for ($i = 0; $i < $iterations; $i++) {
                $simulatedValue = $this->generateSimulatedValue($distribution, $params);
                $results[] = $simulatedValue;

                if ($simulatedValue > $lineValue) {
                    $overCount++;
                }
                $totalValue += $simulatedValue;
            }

            // Calculate statistics
            $overProbability = $overCount / $iterations;
            $expectedValue = $totalValue / $iterations;
            $variance = $this->calculateVariance($results, $expectedValue);
            $stdDev = sqrt($variance);

            // Calculate confidence intervals
            $confidenceIntervals = $this->calculateConfidenceIntervals($results);

            return [
                'over_probability' => $overProbability,
                'expected_value' => $expectedValue,
                'standard_deviation' => $stdDev,
                'confidence_intervals' => $confidenceIntervals,
                'distribution' => $distribution,
                'parameters' => $params,
                'iterations' => $iterations
            ];

        } catch (\Exception $e) {
            Log::error('Monte Carlo simulation failed', [
                'player_id' => $playerId,
                'game_id' => $gameId,
                'stat_type' => $statType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get historical data for a player's stat
     *
     * @param int $playerId Player ID
     * @param string $statType Type of statistic
     * @return array Historical data points
     */
    private function getHistoricalData(int $playerId, string $statType): array
    {
        // Use cache to avoid repeated database queries
        $cacheKey = "historical_data:{$playerId}:{$statType}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($playerId, $statType) {
            // Query database for historical data
            // This is a placeholder - implement actual database query
            return [];
        });
    }

    /**
     * Determine the best distribution type for the data
     *
     * @param array $data Historical data points
     * @return string Distribution type (normal, poisson, binomial)
     */
    private function determineDistributionType(array $data): string
    {
        // Implement distribution type determination logic
        // This could use statistical tests like Kolmogorov-Smirnov
        return 'normal'; // Placeholder
    }

    /**
     * Calculate parameters for the given distribution
     *
     * @param array $data Historical data points
     * @param string $distribution Distribution type
     * @return array Distribution parameters
     * @throws \RuntimeException If distribution type is unsupported
     */
    private function calculateDistributionParameters(array $data, string $distribution): array
    {
        switch ($distribution) {
            case 'normal':
                return [
                    'mean' => array_sum($data) / count($data),
                    'std_dev' => $this->calculateStandardDeviation($data)
                ];
            case 'poisson':
                return [
                    'lambda' => array_sum($data) / count($data)
                ];
            case 'binomial':
                return [
                    'n' => max($data),
                    'p' => array_sum($data) / (count($data) * max($data))
                ];
            default:
                throw new \RuntimeException("Unsupported distribution type: {$distribution}");
        }
    }

    /**
     * Generate a simulated value based on the distribution
     *
     * @param string $distribution Distribution type
     * @param array $params Distribution parameters
     * @return float Simulated value
     * @throws \RuntimeException If distribution type is unsupported
     */
    private function generateSimulatedValue(string $distribution, array $params): float
    {
        switch ($distribution) {
            case 'normal':
                return $this->generateNormalValue($params['mean'], $params['std_dev']);
            case 'poisson':
                return $this->generatePoissonValue($params['lambda']);
            case 'binomial':
                return $this->generateBinomialValue($params['n'], $params['p']);
            default:
                throw new \RuntimeException("Unsupported distribution type: {$distribution}");
        }
    }

    /**
     * Calculate variance of a dataset
     *
     * @param array $data Data points
     * @param float $mean Mean of the data
     * @return float Variance
     */
    private function calculateVariance(array $data, float $mean): float
    {
        $squaredDiffs = array_map(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $data);

        return array_sum($squaredDiffs) / count($data);
    }

    /**
     * Calculate standard deviation of a dataset
     *
     * @param array $data Data points
     * @return float Standard deviation
     */
    private function calculateStandardDeviation(array $data): float
    {
        if (empty($data)) {
            return 0;
        }

        $mean = array_sum($data) / count($data);
        return sqrt($this->calculateVariance($data, $mean));
    }

    /**
     * Calculate confidence intervals for simulation results
     *
     * @param array $data Simulated values
     * @return array Confidence intervals for different levels
     */
    private function calculateConfidenceIntervals(array $data): array
    {
        sort($data);
        $n = count($data);

        return [
            '90' => [
                'lower' => $data[floor($n * 0.05)],
                'upper' => $data[floor($n * 0.95)]
            ],
            '95' => [
                'lower' => $data[floor($n * 0.025)],
                'upper' => $data[floor($n * 0.975)]
            ],
            '99' => [
                'lower' => $data[floor($n * 0.005)],
                'upper' => $data[floor($n * 0.995)]
            ]
        ];
    }

    /**
     * Generate a value from a normal distribution
     *
     * @param float $mean Mean of the distribution
     * @param float $stdDev Standard deviation
     * @return float Generated value
     */
    private function generateNormalValue(float $mean, float $stdDev): float
    {
        // Box-Muller transform
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();

        $z0 = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);

        return $z0 * $stdDev + $mean;
    }

    /**
     * Generate a value from a Poisson distribution
     *
     * @param float $lambda Mean rate of occurrence
     * @return int Generated value
     */
    private function generatePoissonValue(float $lambda): int
    {
        $L = exp(-$lambda);
        $k = 0;
        $p = 1;

        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $L);

        return $k - 1;
    }

    /**
     * Generate a value from a binomial distribution
     *
     * @param int $n Number of trials
     * @param float $p Probability of success
     * @return int Number of successes
     */
    private function generateBinomialValue(int $n, float $p): int
    {
        $successes = 0;

        for ($i = 0; $i < $n; $i++) {
            if (mt_rand() / mt_getrandmax() < $p) {
                $successes++;
            }
        }

        return $successes;
    }

    /**
     * Calculate factorial
     *
     * @param int $n Number to calculate factorial for
     * @return int Factorial value
     */
    private function factorial(int $n): int
    {
        if ($n <= 1) return 1;
        return $n * $this->factorial($n - 1);
    }

    /**
     * Calculate binomial coefficient
     *
     * @param int $n Total number of items
     * @param int $k Number of items to choose
     * @return int Binomial coefficient
     */
    private function binomialCoefficient(int $n, int $k): int
    {
        if ($k < 0 || $k > $n) return 0;
        if ($k == 0 || $k == $n) return 1;

        $k = min($k, $n - $k);
        $c = 1;

        for ($i = 0; $i < $k; $i++) {
            $c = $c * ($n - $i) / ($i + 1);
        }

        return $c;
    }

    /**
     * Calculate normal CDF using error function approximation
     *
     * @param float $x Value to calculate CDF for
     * @return float Cumulative probability
     */
    private function normalCDF(float $x): float
    {
        $a1 = 0.254829592;
        $a2 = -0.284496736;
        $a3 = 1.421413741;
        $a4 = -1.453152027;
        $a5 = 1.061405429;
        $p = 0.3275911;

        $sign = ($x < 0) ? -1 : 1;
        $x = abs($x) / sqrt(2.0);

        $t = 1.0 / (1.0 + $p * $x);
        $erf = 1.0 - ((((($a5 * $t + $a4) * $t) + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$x * $x);

        return 0.5 * (1.0 + $sign * $erf);
    }

    /**
     * Perform multiple regression analysis
     */
    public function performRegressionAnalysis(array $dependentVar, array $independentVars): array
    {
        $n = count($dependentVar);

        if ($n < 2 || empty($independentVars)) {
            return $this->getEmptyRegressionResult();
        }

        // Simple linear regression for single independent variable
        if (count($independentVars) === 1) {
            return $this->simpleLinearRegression($dependentVar, $independentVars[0]);
        }

        // Multiple regression (simplified implementation)
        return $this->multipleLinearRegression($dependentVar, $independentVars);
    }

    /**
     * Analyze performance trends using time series analysis
     */
    public function analyzePerformanceTrends(array $timeSeriesData): array
    {
        if (count($timeSeriesData) < 3) {
            return $this->getEmptyTrendAnalysis();
        }

        $values = array_column($timeSeriesData, 'value');
        $timestamps = array_column($timeSeriesData, 'timestamp');

        return [
            'linear_trend' => $this->calculateLinearTrend($values),
            'moving_average_5' => $this->calculateMovingAverage($values, 5),
            'moving_average_10' => $this->calculateMovingAverage($values, 10),
            'volatility' => $this->calculateVolatility($values),
            'momentum' => $this->calculateMomentum($values),
            'seasonal_patterns' => $this->detectSeasonalPatterns($timeSeriesData),
            'trend_strength' => $this->calculateTrendStrength($values),
            'change_points' => $this->detectChangePoints($values)
        ];
    }

    /**
     * Calculate correlation between two variables
     */
    public function calculateCorrelation(array $var1, array $var2): float
    {
        $n = min(count($var1), count($var2));

        if ($n < 2) {
            return 0;
        }

        // Trim arrays to same length
        $var1 = array_slice($var1, 0, $n);
        $var2 = array_slice($var2, 0, $n);

        $mean1 = array_sum($var1) / $n;
        $mean2 = array_sum($var2) / $n;

        $numerator = 0;
        $sum1Sq = 0;
        $sum2Sq = 0;

        for ($i = 0; $i < $n; $i++) {
            $diff1 = $var1[$i] - $mean1;
            $diff2 = $var2[$i] - $mean2;

            $numerator += $diff1 * $diff2;
            $sum1Sq += $diff1 * $diff1;
            $sum2Sq += $diff2 * $diff2;
        }

        $denominator = sqrt($sum1Sq * $sum2Sq);

        return $denominator > 0 ? $numerator / $denominator : 0;
    }

    /**
     * Calculate Z-score for value normalization
     */
    public function calculateZScore(float $value, float $mean, float $stdDev): float
    {
        return $stdDev > 0 ? ($value - $mean) / $stdDev : 0;
    }

    /**
     * Calculate confidence intervals
     */
    public function calculateConfidenceInterval(array $data, float $confidenceLevel = 0.95): array
    {
        if (empty($data)) {
            return ['lower' => 0, 'upper' => 0, 'margin_of_error' => 0];
        }

        $n = count($data);
        $mean = array_sum($data) / $n;
        $stdDev = $this->calculateStandardDeviation($data);
        $standardError = $stdDev / sqrt($n);

        // Use normal distribution for large samples (n > 30) or t-distribution for small samples
        $criticalValue = $n > 30 ? $this->getZCriticalValue($confidenceLevel) : $this->getTCriticalValue($confidenceLevel, $n - 1);

        $marginOfError = $criticalValue * $standardError;

        return [
            'lower' => $mean - $marginOfError,
            'upper' => $mean + $marginOfError,
            'margin_of_error' => $marginOfError,
            'confidence_level' => $confidenceLevel
        ];
    }

    /**
     * Detect outliers using IQR method
     */
    public function detectOutliers(array $data): array
    {
        if (count($data) < 4) {
            return ['outliers' => [], 'clean_data' => $data];
        }

        sort($data);
        $n = count($data);

        $q1Index = intval($n * 0.25);
        $q3Index = intval($n * 0.75);

        $q1 = $data[$q1Index];
        $q3 = $data[$q3Index];
        $iqr = $q3 - $q1;

        $lowerBound = $q1 - (1.5 * $iqr);
        $upperBound = $q3 + (1.5 * $iqr);

        $outliers = [];
        $cleanData = [];

        foreach ($data as $value) {
            if ($value < $lowerBound || $value > $upperBound) {
                $outliers[] = $value;
            } else {
                $cleanData[] = $value;
            }
        }

        return [
            'outliers' => $outliers,
            'clean_data' => $cleanData,
            'q1' => $q1,
            'q3' => $q3,
            'iqr' => $iqr,
            'lower_bound' => $lowerBound,
            'upper_bound' => $upperBound
        ];
    }

    /**
     * Calculate weighted average with custom weights
     */
    public function calculateWeightedAverage(array $values, array $weights): float
    {
        if (count($values) !== count($weights) || empty($values)) {
            return 0;
        }

        $weightedSum = 0;
        $totalWeight = 0;

        for ($i = 0; $i < count($values); $i++) {
            $weightedSum += $values[$i] * $weights[$i];
            $totalWeight += $weights[$i];
        }

        return $totalWeight > 0 ? $weightedSum / $totalWeight : 0;
    }

    /**
     * Calculate exponentially weighted moving average
     */
    public function calculateEWMA(array $values, float $alpha = 0.3): array
    {
        if (empty($values)) {
            return [];
        }

        $ewma = [$values[0]];

        for ($i = 1; $i < count($values); $i++) {
            $ewma[] = $alpha * $values[$i] + (1 - $alpha) * $ewma[$i - 1];
        }

        return $ewma;
    }

    /**
     * Calculate over probability for a given distribution and line value
     */
    public function calculateOverProbability(array $distribution, float $lineValue): float
    {
        try {
            $distributionType = $distribution['distribution_type'] ?? 'normal';
            $values = $distribution['values'] ?? [];

            if (empty($values)) {
                return 0.5;
            }

            return match($distributionType) {
                'poisson' => $this->calculatePoissonOverProbability(
                    $distribution['lambda'] ?? $distribution['mean'] ?? 0,
                    $lineValue
                ),
                'normal' => $this->calculateNormalOverProbability($distribution, $lineValue),
                'binomial' => $this->calculateBinomialOverProbability($distribution, $lineValue),
                default => $this->calculateEmpiricalOverProbability($values, $lineValue)
            };
        } catch (\Exception $e) {
            Log::error('Error calculating over probability', [
                'error' => $e->getMessage(),
                'distribution' => $distribution,
                'line_value' => $lineValue
            ]);
            return 0.5;
        }
    }

    /**
     * Calculate confidence score based on various factors
     */
    public function calculateConfidence(array $factors): float
    {
        try {
            $sampleSize = $factors['sample_size'] ?? 0;
            $variance = $factors['variance'] ?? 0;
            $consistency = $factors['consistency'] ?? 0;

            // Sample size confidence (0-1)
            $sampleConfidence = min(1, $sampleSize / 50);

            // Variance confidence (0-1)
            $varianceConfidence = max(0, 1 - ($variance / 100));

            // Consistency confidence (0-1)
            $consistencyConfidence = $consistency;

            // Weighted average
            return (
                $sampleConfidence * 0.4 +
                $varianceConfidence * 0.3 +
                $consistencyConfidence * 0.3
            );
        } catch (\Exception $e) {
            Log::error('Error calculating confidence', [
                'error' => $e->getMessage(),
                'factors' => $factors
            ]);
            return 0.5;
        }
    }

    /**
     * Analyze distribution shape to determine appropriate statistical model
     */
    public function analyzeDistributionShape(array $values): array
    {
        try {
            if (empty($values)) {
                return [
                    'symmetry' => 'unknown',
                    'tail_heaviness' => 'unknown',
                    'skewness' => 0,
                    'kurtosis' => 0
                ];
            }

            $mean = array_sum($values) / count($values);
            $variance = $this->calculateVariance($values, $mean);
            $stdDev = sqrt($variance);

            // Calculate skewness
            $skewness = $this->calculateSkewness($values, $mean, $stdDev);

            // Calculate kurtosis
            $kurtosis = $this->calculateKurtosis($values, $mean, $stdDev);

            // Determine symmetry
            $symmetry = abs($skewness) < 0.5 ? 'symmetric' : 'asymmetric';

            // Determine tail heaviness
            $tailHeaviness = $kurtosis > 3 ? 'heavy_tailed' : 'normal_tailed';

            return [
                'symmetry' => $symmetry,
                'tail_heaviness' => $tailHeaviness,
                'skewness' => $skewness,
                'kurtosis' => $kurtosis
            ];
        } catch (\Exception $e) {
            Log::error('Error analyzing distribution shape', [
                'error' => $e->getMessage(),
                'values' => $values
            ]);
            return [
                'symmetry' => 'unknown',
                'tail_heaviness' => 'unknown',
                'skewness' => 0,
                'kurtosis' => 0
            ];
        }
    }

    /**
     * Calculate Normal over probability
     */
    private function calculateNormalOverProbability(array $distribution, float $lineValue): float
    {
        $mean = $distribution['mean'] ?? 0;
        $stdDev = $distribution['std_dev'] ?? 1;

        if ($stdDev <= 0) return 0.5;

        $z = ($lineValue - $mean) / $stdDev;
        return 1 - $this->normalCDF($z);
    }

    /**
     * Calculate Binomial over probability
     */
    private function calculateBinomialOverProbability(array $distribution, float $lineValue): float
    {
        $n = $distribution['n'] ?? 0;
        $p = $distribution['p'] ?? 0.5;

        if ($n <= 0 || $p <= 0 || $p >= 1) return 0.5;

        $probability = 0;
        $k = floor($lineValue);

        // Calculate probability of X > lineValue
        for ($i = 0; $i <= $k; $i++) {
            $probability += $this->binomialCoefficient($n, $i) * pow($p, $i) * pow(1 - $p, $n - $i);
        }

        return 1 - $probability;
    }

    /**
     * Calculate empirical over probability
     */
    private function calculateEmpiricalOverProbability(array $values, float $lineValue): float
    {
        if (empty($values)) return 0.5;

        $count = count($values);
        $overCount = count(array_filter($values, fn($v) => $v > $lineValue));

        return $overCount / $count;
    }

    /**
     * Calculate skewness
     */
    private function calculateSkewness(array $values, float $mean, float $stdDev): float
    {
        if ($stdDev == 0) return 0;

        $n = count($values);
        $sum = 0;

        foreach ($values as $value) {
            $sum += pow(($value - $mean) / $stdDev, 3);
        }

        return ($sum / $n) * sqrt($n * ($n - 1)) / ($n - 2);
    }

    /**
     * Calculate kurtosis
     */
    private function calculateKurtosis(array $values, float $mean, float $stdDev): float
    {
        if ($stdDev == 0) return 0;

        $n = count($values);
        $sum = 0;

        foreach ($values as $value) {
            $sum += pow(($value - $mean) / $stdDev, 4);
        }

        return ($sum / $n) - 3;
    }

    // Private helper methods

    private function calculateLinearTrend(array $values): array
    {
        $n = count($values);
        if ($n < 2) return ['slope' => 0, 'direction' => 'flat'];

        $x = range(1, $n);
        $regression = $this->simpleLinearRegression($values, $x);

        $direction = 'flat';
        if (abs($regression['slope']) > 0.1) {
            $direction = $regression['slope'] > 0 ? 'increasing' : 'decreasing';
        }

        return [
            'slope' => $regression['slope'],
            'direction' => $direction,
            'strength' => abs($regression['correlation'])
        ];
    }

    private function calculateMovingAverage(array $values, int $window): array
    {
        if ($window <= 0 || $window > count($values)) {
            return $values;
        }

        $movingAverages = [];

        for ($i = $window - 1; $i < count($values); $i++) {
            $sum = 0;
            for ($j = $i - $window + 1; $j <= $i; $j++) {
                $sum += $values[$j];
            }
            $movingAverages[] = $sum / $window;
        }

        return $movingAverages;
    }

    private function calculateVolatility(array $values): float
    {
        if (count($values) < 2) return 0;

        $returns = [];
        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i - 1] != 0) {
                $returns[] = ($values[$i] - $values[$i - 1]) / $values[$i - 1];
            }
        }

        return $this->calculateStandardDeviation($returns);
    }

    private function calculateMomentum(array $values): float
    {
        $n = count($values);
        if ($n < 5) return 0;

        $recent = array_slice($values, -5);
        $earlier = array_slice($values, -10, 5);

        if (empty($earlier)) return 0;

        $recentAvg = array_sum($recent) / count($recent);
        $earlierAvg = array_sum($earlier) / count($earlier);

        return $earlierAvg > 0 ? ($recentAvg - $earlierAvg) / $earlierAvg : 0;
    }

    private function detectSeasonalPatterns(array $timeSeriesData): array
    {
        // Simplified seasonal pattern detection
        $patterns = [];
        $values = array_column($timeSeriesData, 'value');

        // Check for weekly patterns (if we have enough data)
        if (count($values) >= 14) {
            $weeklyPattern = $this->calculateWeeklyPattern($timeSeriesData);
            $patterns['weekly'] = $weeklyPattern;
        }

        return $patterns;
    }

    private function calculateWeeklyPattern(array $timeSeriesData): array
    {
        $dayOfWeekAverages = array_fill(0, 7, []);

        foreach ($timeSeriesData as $dataPoint) {
            $dayOfWeek = date('w', strtotime($dataPoint['timestamp']));
            $dayOfWeekAverages[$dayOfWeek][] = $dataPoint['value'];
        }

        $pattern = [];
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        for ($i = 0; $i < 7; $i++) {
            $pattern[$days[$i]] = !empty($dayOfWeekAverages[$i]) ?
                array_sum($dayOfWeekAverages[$i]) / count($dayOfWeekAverages[$i]) : 0;
        }

        return $pattern;
    }

    private function calculateTrendStrength(array $values): float
    {
        $trend = $this->calculateLinearTrend($values);
        return abs($trend['slope']) * $trend['strength'];
    }

    private function detectChangePoints(array $values): array
    {
        // Simplified change point detection using moving averages
        $changePoints = [];
        $window = max(3, intval(count($values) / 10));

        if (count($values) < $window * 2) {
            return $changePoints;
        }

        $movingAvg = $this->calculateMovingAverage($values, $window);

        for ($i = 1; $i < count($movingAvg); $i++) {
            $change = abs($movingAvg[$i] - $movingAvg[$i - 1]);
            $threshold = $this->calculateStandardDeviation($movingAvg) * 1.5;

            if ($change > $threshold) {
                $changePoints[] = [
                    'index' => $i + $window - 1,
                    'magnitude' => $change,
                    'direction' => $movingAvg[$i] > $movingAvg[$i - 1] ? 'increase' : 'decrease'
                ];
            }
        }

        return $changePoints;
    }

    private function getZCriticalValue(float $confidenceLevel): float
    {
        // Common critical values for normal distribution
        $criticalValues = [
            0.90 => 1.645,
            0.95 => 1.96,
            0.99 => 2.576
        ];

        return $criticalValues[$confidenceLevel] ?? 1.96;
    }

    private function getTCriticalValue(float $confidenceLevel, int $degreesOfFreedom): float
    {
        // Simplified t-distribution critical values (would need full t-table for precision)
        $alpha = 1 - $confidenceLevel;

        if ($degreesOfFreedom >= 30) {
            return $this->getZCriticalValue($confidenceLevel);
        }

        // Approximate t-values for common confidence levels and small df
        $tValues = [
            0.95 => [1 => 12.706, 2 => 4.303, 3 => 3.182, 4 => 2.776, 5 => 2.571, 10 => 2.228, 20 => 2.086],
            0.99 => [1 => 63.657, 2 => 9.925, 3 => 5.841, 4 => 4.604, 5 => 4.032, 10 => 3.169, 20 => 2.845]
        ];

        return $tValues[$confidenceLevel][$degreesOfFreedom] ?? $this->getZCriticalValue($confidenceLevel);
    }

    private function getEmptyRegressionResult(): array
    {
        return [
            'slope' => 0,
            'intercept' => 0,
            'r_squared' => 0,
            'correlation' => 0,
            'error' => 'Insufficient data for regression analysis'
        ];
    }

    private function getEmptyTrendAnalysis(): array
    {
        return [
            'linear_trend' => ['slope' => 0, 'direction' => 'flat', 'strength' => 0],
            'moving_average_5' => [],
            'moving_average_10' => [],
            'volatility' => 0,
            'momentum' => 0,
            'seasonal_patterns' => [],
            'trend_strength' => 0,
            'change_points' => []
        ];
    }

    /**
     * Perform simple linear regression
     *
     * @param array $y Dependent variable values
     * @param array $x Independent variable values
     * @return array Regression results including slope, intercept, and correlation
     */
    private function simpleLinearRegression(array $y, array $x): array
    {
        $n = count($y);
        if ($n !== count($x) || $n < 2) {
            return $this->getEmptyRegressionResult();
        }

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumXX += $x[$i] * $x[$i];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Calculate correlation coefficient
        $meanX = $sumX / $n;
        $meanY = $sumY / $n;
        $sumSqX = 0;
        $sumSqY = 0;
        $sumSqXY = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumSqX += pow($x[$i] - $meanX, 2);
            $sumSqY += pow($y[$i] - $meanY, 2);
            $sumSqXY += ($x[$i] - $meanX) * ($y[$i] - $meanY);
        }

        $correlation = $sumSqXY / sqrt($sumSqX * $sumSqY);
        $rSquared = $correlation * $correlation;

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => $rSquared,
            'correlation' => $correlation
        ];
    }

    /**
     * Perform multiple linear regression
     *
     * @param array $y Dependent variable values
     * @param array $x Independent variables (array of arrays)
     * @return array Regression results including coefficients and statistics
     */
    private function multipleLinearRegression(array $y, array $x): array
    {
        $n = count($y);
        $k = count($x);

        if ($n < $k + 1) {
            return $this->getEmptyRegressionResult();
        }

        // Add constant term (1) to each observation
        $X = array_fill(0, $n, [1]);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $k; $j++) {
                $X[$i][] = $x[$j][$i];
            }
        }

        // Calculate X'X and X'y
        $XtX = $this->matrixMultiply($this->matrixTranspose($X), $X);
        $Xty = $this->matrixMultiply($this->matrixTranspose($X), array_map(fn($val) => [$val], $y));

        // Calculate coefficients using normal equations
        $coefficients = $this->solveNormalEquations($XtX, $Xty);

        // Calculate R-squared
        $yMean = array_sum($y) / $n;
        $totalSS = 0;
        $residualSS = 0;

        for ($i = 0; $i < $n; $i++) {
            $predicted = $coefficients[0];
            for ($j = 0; $j < $k; $j++) {
                $predicted += $coefficients[$j + 1] * $x[$j][$i];
            }
            $totalSS += pow($y[$i] - $yMean, 2);
            $residualSS += pow($y[$i] - $predicted, 2);
        }

        $rSquared = 1 - ($residualSS / $totalSS);

        return [
            'coefficients' => $coefficients,
            'r_squared' => $rSquared,
            'standard_errors' => $this->calculateStandardErrors($X, $residualSS, $n, $k),
            'f_statistic' => $this->calculateFStatistic($totalSS, $residualSS, $n, $k)
        ];
    }

    /**
     * Matrix multiplication helper
     */
    private function matrixMultiply(array $a, array $b): array
    {
        $rowsA = count($a);
        $colsA = count($a[0]);
        $colsB = count($b[0]);
        $result = array_fill(0, $rowsA, array_fill(0, $colsB, 0));

        for ($i = 0; $i < $rowsA; $i++) {
            for ($j = 0; $j < $colsB; $j++) {
                for ($k = 0; $k < $colsA; $k++) {
                    $result[$i][$j] += $a[$i][$k] * $b[$k][$j];
                }
            }
        }

        return $result;
    }

    /**
     * Matrix transpose helper
     */
    private function matrixTranspose(array $matrix): array
    {
        $rows = count($matrix);
        $cols = count($matrix[0]);
        $transposed = array_fill(0, $cols, array_fill(0, $rows, 0));

        for ($i = 0; $i < $rows; $i++) {
            for ($j = 0; $j < $cols; $j++) {
                $transposed[$j][$i] = $matrix[$i][$j];
            }
        }

        return $transposed;
    }

    /**
     * Solve normal equations using Gaussian elimination
     */
    private function solveNormalEquations(array $XtX, array $Xty): array
    {
        $n = count($XtX);
        $augmented = array_fill(0, $n, array_fill(0, $n + 1, 0));

        // Create augmented matrix
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $augmented[$i][$j] = $XtX[$i][$j];
            }
            $augmented[$i][$n] = $Xty[$i][0];
        }

        // Gaussian elimination
        for ($i = 0; $i < $n; $i++) {
            $maxRow = $i;
            for ($j = $i + 1; $j < $n; $j++) {
                if (abs($augmented[$j][$i]) > abs($augmented[$maxRow][$i])) {
                    $maxRow = $j;
                }
            }

            // Swap rows
            $temp = $augmented[$i];
            $augmented[$i] = $augmented[$maxRow];
            $augmented[$maxRow] = $temp;

            // Eliminate
            for ($j = $i + 1; $j < $n; $j++) {
                $factor = $augmented[$j][$i] / $augmented[$i][$i];
                for ($k = $i; $k <= $n; $k++) {
                    $augmented[$j][$k] -= $factor * $augmented[$i][$k];
                }
            }
        }

        // Back substitution
        $x = array_fill(0, $n, 0);
        for ($i = $n - 1; $i >= 0; $i--) {
            $sum = 0;
            for ($j = $i + 1; $j < $n; $j++) {
                $sum += $augmented[$i][$j] * $x[$j];
            }
            $x[$i] = ($augmented[$i][$n] - $sum) / $augmented[$i][$i];
        }

        return $x;
    }

    /**
     * Calculate standard errors of regression coefficients
     */
    private function calculateStandardErrors(array $X, float $residualSS, int $n, int $k): array
    {
        $mse = $residualSS / ($n - $k - 1);
        $XtX = $this->matrixMultiply($this->matrixTranspose($X), $X);
        $XtXInv = $this->matrixInverse($XtX);

        $standardErrors = [];
        for ($i = 0; $i <= $k; $i++) {
            $standardErrors[] = sqrt($mse * $XtXInv[$i][$i]);
        }

        return $standardErrors;
    }

    /**
     * Calculate F-statistic for regression
     */
    private function calculateFStatistic(float $totalSS, float $residualSS, int $n, int $k): float
    {
        $regressionSS = $totalSS - $residualSS;
        $dfRegression = $k;
        $dfResidual = $n - $k - 1;

        if ($dfResidual <= 0 || $residualSS == 0) {
            return 0;
        }

        return ($regressionSS / $dfRegression) / ($residualSS / $dfResidual);
    }

    /**
     * Matrix inverse using Gaussian elimination
     */
    private function matrixInverse(array $matrix): array
    {
        $n = count($matrix);
        $inverse = array_fill(0, $n, array_fill(0, $n, 0));

        // Create augmented matrix [A|I]
        $augmented = array_fill(0, $n, array_fill(0, 2 * $n, 0));
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $augmented[$i][$j] = $matrix[$i][$j];
            }
            $augmented[$i][$i + $n] = 1;
        }

        // Gaussian elimination
        for ($i = 0; $i < $n; $i++) {
            $maxRow = $i;
            for ($j = $i + 1; $j < $n; $j++) {
                if (abs($augmented[$j][$i]) > abs($augmented[$maxRow][$i])) {
                    $maxRow = $j;
                }
            }

            // Swap rows
            $temp = $augmented[$i];
            $augmented[$i] = $augmented[$maxRow];
            $augmented[$maxRow] = $temp;

            // Scale row
            $scale = $augmented[$i][$i];
            for ($j = 0; $j < 2 * $n; $j++) {
                $augmented[$i][$j] /= $scale;
            }

            // Eliminate
            for ($j = 0; $j < $n; $j++) {
                if ($j != $i) {
                    $factor = $augmented[$j][$i];
                    for ($k = 0; $k < 2 * $n; $k++) {
                        $augmented[$j][$k] -= $factor * $augmented[$i][$k];
                    }
                }
            }
        }

        // Extract inverse
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $inverse[$i][$j] = $augmented[$i][$j + $n];
            }
        }

        return $inverse;
    }
}
