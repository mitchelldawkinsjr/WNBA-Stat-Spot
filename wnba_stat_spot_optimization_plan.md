
# WNBA Stat Spot Optimization & Cleanup Plan

## Phase 1: Cleanup of Unused & Redundant Files

### Actions:
- Delete OS artifacts like `.DS_Store` across the repo.
- Remove unused frontend template files:
  - `resources/js/app.js`, `bootstrap.js`
  - `resources/css/app.css`
  - `SocialFriends.svelte`, `Chat`, `Calendar`, and similar non-WNBA-related components
  - SCSS files for removed templates (e.g. `_app-social.scss`, `_app-email.scss`)
- Remove redundant Docker setup files:
  - Eliminate `resources/js/Dockerfile`
  - Consolidate or remove either `docker/` or `.docker/`
- Delete unused deployment configs:
  - If deploying on Render, remove `railway.json`, `fly.toml`, etc.
- Delete unused console commands:
  - `TestWnbaDataService.php` if `ImportWnbaData.php` covers the functionality

### Outcome:
- Reduced project size
- Clearer repo structure
- Lower cognitive load for new developers

---

## Phase 2: Refactor Duplicate & AI-Generated Code

### Actions:
- Audit and consolidate these classes:
  - `PropsPredictionService`, `StatisticalEngineService`, `PropScannerController`, `WnbaPredictionsController`
    - Centralize shared logic into a core prediction engine/service
- Remove or comment out unused analytics classes:
  - e.g., `PoissonCalculator`, `BayesianCalculator`, `RegressionAnalyzer` if not in use
- Refactor `WnbaDataService`:
  - Split CSV handling and DB insert logic
  - Move into `App\Services\WNBA\Data\ImporterService.php`

### Outcome:
- Less duplicated logic
- Clearer analytics engine
- Improved testability and reusability

---

## Phase 3: Performance Optimization

### Actions:
- Refactor CSV imports (use chunking or streaming)
  - Avoid `memory_get_usage()` spikes
- Optimize prop scanner endpoint (`scan-all`):
  - Cache results with `Cache::remember()` in Laravel
  - Offload to a queued job
- Review use of Monte Carlo simulations:
  - Limit iterations or run offline with scheduled job
- Add indexes to `stats`, `players`, `teams` where missing
- Use eager loading to avoid N+1 queries

### Outcome:
- Faster API responses
- More scalable backend
- Reduced server load during high traffic

---

## Phase 4: Codebase Structure & Dev Ergonomics

### Actions:
- Normalize naming conventions:
  - Rename `WnbaPredictionsController` to `PredictionsController`
  - Drop `Wnba` prefix in internal services where context is clear
- Move controllers:
  - Place API controllers under `Http\Controllers\Api\Wnba`
  - Move `Main.php` to `Http\Controllers\Web\AppController.php`
- Standardize services folder:
  - `Services\WNBA\Analytics`, `Services\WNBA\Data`, `Services\WNBA\Math`
- Add PHPDoc comments to:
  - Monte Carlo, Bayesian, and prediction services
- Add and verify `.php-cs-fixer` config and use Prettier for Svelte

### Outcome:
- Developer-friendly repo
- Consistent architecture
- Easier onboarding for collaborators

---

## Phase 5: Frontend UI & Visualization

### Actions:
- Implement visual analytics using existing chart components:
  - Player Page: Line chart for game-by-game points
  - Team Page: Bar chart for team win streaks or average points
  - Prop Scanner: Bar chart for predicted vs. betting line
- Add “Prediction vs. Actual” line graph to validation page
- Add a “Top Players” leaderboard with dynamic bar chart
- Implement SvelteKit stores for player comparison
- Ensure all charts are responsive for mobile users

### Outcome:
- More engaging, data-rich experience for users
- Intuitive visual insights from predictions
- Differentiation from other sports apps

---

## Phase 6: Testing & Stability

### Actions:
- Add unit tests for:
  - `PropsPredictionService`, `MonteCarloSimulator`, `StatisticalEngineService`
- Create integration tests for:
  - `/api/wnba/prop-scanner/scan-all`
  - `/api/predictions` endpoint
- Add runtime logging for:
  - Prediction accuracy
  - Long-running job durations

### Outcome:
- Increased confidence in model performance
- Safer future refactoring
- Early warnings on runtime issues

---

## Weekly Timeline

**Week 1**  
- Phases 1 and 2

**Week 2**  
- Phases 3 and 4

**Week 3**  
- Phase 5

**Week 4**  
- Phase 6 and final review
