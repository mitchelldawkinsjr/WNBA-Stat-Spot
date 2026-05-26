# 🚀 WNBA Stat Spot CI/CD Pipeline

This directory contains a comprehensive CI/CD pipeline for the WNBA Stat Spot application. The pipeline provides automated testing, security scanning, deployment, performance monitoring, and dependency management.

## 📋 Workflow Overview

### Core Workflows

| Workflow | Purpose | Triggers | Duration |
|----------|---------|----------|----------|
| **CI Pipeline** | Code testing & validation | Push, PR | ~8-12 min |
| **Security Scan** | Vulnerability detection | Push, PR, Weekly | ~5-8 min |
| **Deploy** | Production deployment | Push to main, Manual | ~10-15 min |
| **Release** | Version management | Manual | ~15-20 min |

### Quality & Monitoring Workflows

| Workflow | Purpose | Triggers | Duration |
|----------|---------|----------|----------|
| **PR Quality** | Pull request validation | PR events | ~5-7 min |
| **Performance** | Load & speed testing | Push, PR, Weekly | ~10-15 min |
| **Health Check** | System monitoring | Every 30 min | ~2-3 min |
| **Dependency Management** | Updates & security | Weekly, Manual | ~5-10 min |

### Utility Workflows

| Workflow | Purpose | Triggers | Duration |
|----------|---------|----------|----------|
| **Ping Endpoint** | Keep services alive | Every 12 min | <1 min |

## 🔧 Workflow Details

### 1. CI Pipeline (`ci.yml`)

**Purpose:** Comprehensive testing and validation of code changes.

**Features:**
- ✅ PHP and JavaScript dependency management
- ✅ Database migrations and seeding
- ✅ Code style checking (Laravel Pint)
- ✅ Frontend linting and type checking
- ✅ Test execution with coverage reporting
- ✅ Docker image building and testing
- ✅ Build artifact creation

**Triggers:**
- Push to `main`, `develop`, `refactor-main`
- Pull requests to `main`, `develop`

**Services:**
- PostgreSQL 15 (testing database)
- Redis (caching and sessions)

---

### 2. Security Scan (`security.yml`)

**Purpose:** Comprehensive security analysis and vulnerability detection.

**Features:**
- 🔒 PHP dependency security scanning
- 🔒 NPM vulnerability detection
- 🔒 Static code analysis (Semgrep)
- 🔒 Secret detection (TruffleHog)
- 🔒 Container security scanning (Trivy)
- 🔒 Infrastructure security (Checkov)
- 🔒 License compliance checking

**Triggers:**
- Push to `main`, `develop`, `refactor-main`
- Pull requests
- Weekly schedule (Monday 2 AM UTC)
- Manual dispatch

---

### 3. Deploy (`deploy.yml`)

**Purpose:** Automated deployment to staging and production environments.

**Features:**
- 🚀 Staging deployment (automatic)
- 🚀 Production deployment (manual approval)
- 🚀 Database backup before deployment
- 🚀 Comprehensive health checks
- 🚀 Automatic rollback on failure
- 🚀 Slack notifications

**Environments:**
- **Staging:** Automatic deployment from `main`
- **Production:** Manual approval required

**Rollback:** Automatic rollback to previous commit if deployment fails.

---

### 4. Release (`release.yml`)

**Purpose:** Automated release management with semantic versioning.

**Features:**
- 📦 Semantic version generation
- 📦 Changelog generation
- 📦 GitHub release creation
- 📦 Docker image tagging
- 📦 Release asset packaging
- 📦 Slack notifications

**Version Types:**
- `patch` - Bug fixes (1.0.0 → 1.0.1)
- `minor` - New features (1.0.0 → 1.1.0)
- `major` - Breaking changes (1.0.0 → 2.0.0)
- `prerelease` - RC versions (1.0.0 → 1.1.0-rc.timestamp)

---

### 5. PR Quality (`pr-quality.yml`)

**Purpose:** Automated pull request quality checks and review assistance.

**Features:**
- ✨ PR title format validation
- ✨ Breaking change detection
- ✨ File size monitoring
- ✨ Security issue scanning
- ✨ Code coverage requirements
- ✨ Automatic labeling
- ✨ Review checklist generation

**Requirements:**
- PR titles must follow conventional commit format
- Code coverage minimum: 70%
- No files larger than 5MB

---

### 6. Performance (`performance.yml`)

**Purpose:** Comprehensive performance testing and monitoring.

**Features:**
- ⚡ Lighthouse performance audits
- ⚡ Load testing with Artillery
- ⚡ API endpoint performance testing
- ⚡ Database query performance
- ⚡ Stress and spike testing
- ⚡ Performance threshold validation

**Test Types:**
- `basic` - Quick performance check
- `load` - Sustained load testing
- `stress` - High load testing
- `spike` - Traffic spike simulation
- `endurance` - Long-duration testing

---

### 7. Health Check (`health-check.yml`)

**Purpose:** Continuous application health monitoring and alerting.

**Features:**
- 🏥 Comprehensive health endpoints creation
- 🏥 Database connectivity monitoring
- 🏥 Cache system verification
- 🏥 Queue system health checks
- 🏥 SSL certificate monitoring
- 🏥 Automatic issue creation on failures
- 🏥 Slack alerting

**Health Endpoints:**
- `/api/health` - Basic health check
- `/api/health/detailed` - Comprehensive system health
- `/api/health/database` - Database connectivity
- `/api/health/cache` - Cache system health
- `/api/health/queue` - Queue system health

---

### 8. Dependency Management (`dependency-management.yml`)

**Purpose:** Automated dependency updates and security patching.

**Features:**
- 📦 PHP dependency analysis
- 📦 NPM dependency monitoring
- 📦 Security vulnerability detection
- 📦 Automatic update PRs
- 📦 Critical security alerts
- 📦 Dependency cleanup

**Update Types:**
- `security` - Critical security patches only
- `patch` - Patch version updates
- `minor` - Minor version updates
- `major` - Major version updates (review required)

---

## 🔐 Required Secrets

### Repository Secrets

```bash
# Deployment
RAILWAY_TOKEN                 # Railway deployment token
RAILWAY_SERVICE_ID           # Railway service identifier
RENDER_API_KEY               # Render API key
RENDER_SERVICE_ID            # Render service identifier

# Database
DATABASE_URL                 # Production database URL

# URLs
STAGING_URL                  # Staging environment URL
PRODUCTION_URL               # Production environment URL

# External APIs
THE_ODDS_API_KEY            # The Odds API key

# Notifications
SLACK_WEBHOOK               # Slack webhook for notifications

# Code Quality
CODECOV_TOKEN               # Codecov upload token
```

### Repository Variables

```bash
# Environment URLs (alternative to secrets)
STAGING_URL                 # Staging environment URL
PRODUCTION_URL              # Production environment URL
```

## 🚀 Usage Guide

### Running Workflows Manually

#### 1. Deploy to Production
```bash
# Via GitHub UI
Actions → Deploy → Run workflow
- Environment: production

# Via CLI
gh workflow run deploy.yml -f environment=production
```

#### 2. Create Release
```bash
# Via GitHub UI
Actions → Release → Run workflow
- Version type: patch/minor/major
- Prerelease: false

# Via CLI
gh workflow run release.yml -f version_type=minor -f prerelease=false
```

#### 3. Performance Testing
```bash
# Via GitHub UI
Actions → Performance Testing → Run workflow
- Test type: load

# Via CLI
gh workflow run performance.yml -f test_type=load
```

#### 4. Dependency Updates
```bash
# Via GitHub UI
Actions → Dependency Management → Run workflow
- Update type: security

# Via CLI
gh workflow run dependency-management.yml -f update_type=security
```

### Monitoring Health

#### Check Application Health
```bash
# Production
curl https://your-production-url.com/api/health/detailed

# Staging
curl https://your-staging-url.com/api/health/detailed
```

#### View Workflow Status
```bash
# Recent workflow runs
gh run list

# Specific workflow
gh run list --workflow=ci.yml

# Watch workflow run
gh run watch
```

## 📊 Performance Thresholds

### Quality Gates

| Metric | Threshold | Action |
|--------|-----------|--------|
| Code Coverage | ≥ 70% | Fail PR if below |
| Lighthouse Score | ≥ 70/100 | Warning if below |
| API Response Time | ≤ 500ms | Warning if above |
| Load Test P95 | ≤ 2000ms | Fail if above |
| SSL Certificate | > 7 days | Alert if expiring |

### Resource Limits

| Resource | Limit | Monitoring |
|----------|-------|------------|
| File Size | 5MB | Block large files |
| PR Size | 500 lines | Warning for large PRs |
| Test Duration | 10 minutes | Timeout long tests |
| Deployment | 15 minutes | Timeout deployments |

## 🔧 Customization

### Adding New Environments

1. **Create environment in GitHub:**
   ```bash
   Settings → Environments → New environment
   ```

2. **Add environment secrets:**
   ```bash
   DATABASE_URL
   STAGING_URL or PRODUCTION_URL
   ```

3. **Update deployment workflow:**
   ```yaml
   # Add new environment job in deploy.yml
   deploy-new-env:
     name: Deploy to New Environment
     environment: new-env
   ```

### Modifying Performance Thresholds

Edit the thresholds in `performance.yml`:

```yaml
# Performance thresholds
if (( $(echo "$PERF_SCORE < 80" | bc -l) )); then  # Increased from 70
  echo "❌ Performance score is below 80"
  exit 1
fi
```

### Adding New Health Checks

Add to the `HealthController.php`:

```php
private function checkNewService(): array
{
    try {
        // Your health check logic
        return ['status' => 'healthy'];
    } catch (Exception $e) {
        return ['status' => 'unhealthy', 'error' => $e->getMessage()];
    }
}
```

### Custom Notifications

Add Slack webhook configurations:

```yaml
- name: Custom Notification
  uses: 8398a7/action-slack@v3
  with:
    status: custom
    custom_payload: |
      {
        "channel": "#your-channel",
        "text": "Custom message"
      }
    webhook_url: ${{ secrets.SLACK_WEBHOOK }}
```

## 🐛 Troubleshooting

### Common Issues

#### 1. **Deployment Failures**
```bash
# Check deployment logs
gh run view --log

# Verify secrets are set
gh secret list

# Test health endpoints
curl -f https://your-url.com/api/health
```

#### 2. **Test Failures**
```bash
# Run tests locally
php artisan test
npm test

# Check database connectivity
php artisan migrate:status
```

#### 3. **Security Scan Issues**
```bash
# Update dependencies
composer update
npm update

# Check for known vulnerabilities
composer audit
npm audit
```

#### 4. **Performance Issues**
```bash
# Profile application locally
php artisan route:cache
php artisan config:cache
php artisan view:cache

# Check database queries
php artisan telescope:install
```

### Getting Help

1. **Check workflow logs:** GitHub Actions → Failed workflow → View logs
2. **Review documentation:** Each workflow has detailed comments
3. **Check health endpoints:** Use `/api/health/detailed` for diagnostics
4. **Monitor Slack alerts:** Critical issues are automatically reported

## 📈 Metrics & Monitoring

### Key Metrics Tracked

- **Deployment Success Rate:** Target > 95%
- **Test Pass Rate:** Target > 98%
- **Security Scan Clean Rate:** Target 100%
- **Performance Score:** Target > 80
- **Health Check Uptime:** Target > 99.9%

### Monitoring Dashboards

- **GitHub Actions:** Workflow execution history
- **Codecov:** Code coverage trends
- **Slack Alerts:** Real-time notifications
- **Health Endpoints:** System status monitoring

## 🔄 Continuous Improvement

The CI/CD pipeline is designed to evolve with your needs:

1. **Regular Updates:** Workflows are automatically updated via dependency management
2. **Performance Optimization:** Thresholds and targets are adjusted based on application growth
3. **Security Enhancement:** New scanning tools and rules are added regularly
4. **Monitoring Expansion:** Additional health checks and metrics are added as needed

---

**Last Updated:** December 2024  
**Pipeline Version:** 1.0.0  
**Maintainer:** GitHub Actions Automation 
