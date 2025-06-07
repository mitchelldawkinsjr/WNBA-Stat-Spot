# 🆓 **FREE WNBA Deployment Guide** (Railway + Supabase)

Deploy your WNBA Analytics application **completely FREE** using Railway's free tier and Supabase's free PostgreSQL.

## 💰 **Cost Breakdown: $0/month**

| Service | Free Tier Limits | Cost |
|---------|------------------|------|
| **Railway** | 500 execution hours/month | $0 |
| **Supabase** | 500MB PostgreSQL + 2 concurrent connections | $0 |
| **Upstash Redis** | 10K commands/day | $0 |
| **Total** | | **$0** |

## 🎯 **What You Get For Free:**

- ✅ **Full WNBA Analytics Application**
- ✅ **PostgreSQL Database** (500MB)
- ✅ **Redis Cache** (10K commands/day)
- ✅ **SSL Certificate**
- ✅ **Auto-deployments from Git**
- ✅ **Custom domain support**

## 🚀 **Step-by-Step FREE Deployment**

### **1. Setup Supabase (Free PostgreSQL)**

1. **Sign up**: Go to [supabase.com](https://supabase.com)
2. **Create Project**: 
   - Project name: `wnba-stat-spot`
   - Database password: (save this!)
   - Region: Choose closest to you
3. **Get Connection Details**:
   - Go to **Settings → Database**
   - Copy connection string (looks like): 
     ```
     postgresql://postgres:[password]@db.[project-id].supabase.co:5432/postgres
     ```

### **2. Setup Upstash Redis (Free)**

1. **Sign up**: Go to [upstash.com](https://upstash.com)
2. **Create Database**:
   - Name: `wnba-redis`
   - Region: Choose same as Supabase
   - Type: Regional (free)
3. **Get Connection Details**:
   - Copy `UPSTASH_REDIS_REST_URL`
   - Copy `UPSTASH_REDIS_REST_TOKEN`

### **3. Deploy to Railway (Free)**

1. **Sign up**: Go to [railway.app](https://railway.app)
2. **Create Project**: 
   - Click "New Project"
   - Select "Deploy from GitHub repo"
   - Connect your WNBA repository
3. **Railway will auto-detect your Dockerfile**

### **4. Configure Environment Variables**

In Railway dashboard, add these variables:

```bash
# Database (from Supabase)
DATABASE_URL=postgresql://postgres:[password]@db.[project-id].supabase.co:5432/postgres
DB_CONNECTION=pgsql

# Redis (from Upstash)
REDIS_URL=rediss://default:[token]@[endpoint]:6379
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# App Settings
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your_generated_key_here

# The Odds API (get free key from the-odds-api.com)
THE_ODDS_API_KEY=your_free_api_key

# WNBA Settings
WNBA_CACHE_ENABLED=true
WNBA_CURRENT_SEASON=2025
WNBA_LOGGING_ENABLED=true
```

### **5. Initial Setup**

After deployment, run these commands in Railway's console:

```bash
# Run migrations
php artisan migrate --force

# Import WNBA data (be careful with free limits!)
php artisan app:import-wnba-data --force

# Generate app key if needed
php artisan key:generate
```

## 📊 **FREE Tier Limitations & Optimizations**

### **Railway Free Limits:**
- **500 execution hours/month** (~20 days if always running)
- **1GB RAM, 1 vCPU**
- **1GB disk space**

### **Optimization Strategies:**

#### **1. Reduce Memory Usage**
```bash
# Add to .env
WNBA_MEMORY_LIMIT=256M
WNBA_BATCH_SIZE=100
WNBA_MAX_CONCURRENT_REQUESTS=3
```

#### **2. Optimize Caching**
```bash
# Aggressive caching for free tier
WNBA_CACHE_TTL=7200
WNBA_PLAYER_STATS_TTL=3600
WNBA_PREDICTIONS_TTL=1800
```

#### **3. Reduce Database Load**
```bash
# Import data less frequently
# Use the scheduler to import only once daily
# Comment out frequent updates in development
```

## 🔄 **Alternative FREE Combinations**

### **Option A: Fly.io Free + Supabase**
```yaml
Cost: $0/month
- Web: Fly.io (3 shared VMs free)
- DB: Supabase PostgreSQL (500MB free)  
- Redis: Fly.io Redis (free tier)
```

### **Option B: Vercel + Railway API + Supabase**
```yaml
Cost: $0/month
- Frontend: Vercel (unlimited free)
- API: Railway (500h free)
- DB: Supabase (500MB free)
```

### **Option C: Firebase + Railway**
```yaml
Cost: $0/month
- Frontend: Firebase Hosting (free)
- API: Railway (500h free)  
- DB: Firebase Firestore (free tier)
```

## 💸 **When to Upgrade (Low-Cost Options)**

When you outgrow free tiers:

### **Railway Pro** ($5-15/month)
```yaml
- Usage-based pricing
- No execution hour limits
- More memory/CPU
- Better for production
```

### **DigitalOcean Droplet** ($6/month)
```yaml
- 1GB RAM, 1 vCPU, 25GB SSD
- Full control
- Install Docker + your app
- Best value for money
```

### **AWS/GCP Free Tier** (12 months free)
```yaml
- EC2 t2.micro / Compute Engine f1-micro
- RDS PostgreSQL free tier
- ElastiCache Redis
- Great for learning cloud platforms
```

## 🛠️ **Setting Up DigitalOcean VPS (Ultra-Cheap)**

If you want the **cheapest long-term solution** ($6/month):

### **1. Create Droplet**
```bash
# Choose:
- OS: Ubuntu 22.04 LTS
- Plan: Basic ($6/month - 1GB/1CPU/25GB)
- Datacenter: Closest to your users
```

### **2. Install Dependencies**
```bash
# SSH into droplet
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Install Docker Compose
sudo apt install docker-compose-plugin
```

### **3. Deploy Application**
```bash
# Clone your repo
git clone https://github.com/your-username/wnba-stat-spot.git
cd wnba-stat-spot

# Use your existing docker-compose.yml
docker compose up -d

# Setup database
docker exec wnba-stat-spot-laravel.test-1 php artisan migrate --force
docker exec wnba-stat-spot-laravel.test-1 php artisan app:import-wnba-data --force
```

## 🎯 **Recommendations by Use Case**

### **For Learning/Development**
- ✅ **Railway Free + Supabase** (easiest)
- ✅ **Fly.io Free**

### **For Small Production**
- ✅ **Railway Pro** ($8-15/month, usage-based)
- ✅ **DigitalOcean VPS** ($6/month, more control)

### **For Scaling Up**
- ✅ **Render** ($21/month, managed)
- ✅ **AWS/GCP** (pay-as-you-scale)

## 🔗 **Quick Start Links**

- **Railway**: [railway.app](https://railway.app)
- **Supabase**: [supabase.com](https://supabase.com)
- **Upstash**: [upstash.com](https://upstash.com)
- **DigitalOcean**: [digitalocean.com](https://digitalocean.com)
- **The Odds API**: [the-odds-api.com](https://the-odds-api.com) (free tier available)

---

## 🎉 **Start FREE Today!**

**Best Path for Beginners:**
1. Start with **Railway Free + Supabase** ($0)
2. Test your application and get users
3. Upgrade to **Railway Pro** when you need more resources
4. Scale to **Render or VPS** when you have steady traffic

**Your WNBA app can run FREE for months while you build your user base! 🏀📊** 
