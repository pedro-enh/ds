# Database Setup Guide - Discord Broadcaster Pro

## Problem: Data Loss on Restart/Redeploy

The issue you're experiencing is that SQLite database files are stored locally and get deleted when you restart or redeploy your application on cloud platforms. This guide will help you set up a persistent database.

## Solution Options

### Option 1: Use Cloud Database (Recommended)

#### For Railway/Heroku/Similar Platforms:

1. **Add a PostgreSQL Database**:
   - Railway: Go to your project → Add Service → Database → PostgreSQL
   - Heroku: Add PostgreSQL addon from the dashboard
   - This will automatically set the `DATABASE_URL` environment variable

2. **Deploy Your Updated Code**:
   - The updated `database.php` will automatically detect the `DATABASE_URL` and use PostgreSQL
   - Your data will persist across restarts and redeploys

#### For Other Cloud Platforms:

1. **Get a PostgreSQL Database**:
   - Use services like ElephantSQL, Supabase, or Neon
   - Get the connection URL (format: `postgresql://username:password@host:port/database`)

2. **Set Environment Variable**:
   - Set `DATABASE_URL` to your PostgreSQL connection string
   - Example: `postgresql://user:pass@host:5432/dbname`

### Option 2: Use MySQL Database

1. **Get MySQL Database**:
   - Use services like PlanetScale, MySQL on Railway, or any MySQL provider
   - Get connection URL (format: `mysql://username:password@host:port/database`)

2. **Set Environment Variable**:
   - Set `DATABASE_URL` to your MySQL connection string

### Option 3: Local Development with Persistent SQLite

If you're running locally and want to keep SQLite:

1. **Set DB_PATH Environment Variable**:
   ```bash
   export DB_PATH="/path/to/persistent/broadcaster.db"
   ```

2. **Make sure the directory is writable and persistent**

## Setup Instructions

### Step 1: Update Your Environment

Add one of these environment variables to your deployment:

```bash
# For PostgreSQL
DATABASE_URL=postgresql://username:password@host:port/database

# For MySQL  
DATABASE_URL=mysql://username:password@host:port/database

# For SQLite (local only)
DB_PATH=/persistent/path/broadcaster.db
```

### Step 2: Test Database Setup

Run the database setup script to verify everything works:

```bash
php setup-database.php
```

This will:
- Test database connection
- Create all required tables
- Verify CRUD operations
- Show database type and environment info

### Step 3: Deploy

Deploy your application with the new database configuration. Your data will now persist across restarts!

## Database Features

The updated database system supports:

✅ **Multi-Database Support**: SQLite, PostgreSQL, MySQL
✅ **Automatic Migration**: Tables created automatically
✅ **Data Persistence**: No more data loss on restart
✅ **Cloud-Ready**: Works with all major cloud platforms
✅ **Backward Compatible**: Still works with SQLite for local development

## Verification

After deployment, check that your database is working:

1. **Login to your app** - User data should be saved
2. **Add credits** - Credits should persist after restart
3. **Check transactions** - Transaction history should be maintained
4. **Restart your app** - All data should still be there

## Troubleshooting

### Database Connection Issues

1. **Check DATABASE_URL format**:
   ```bash
   # Correct formats:
   postgresql://user:pass@host:5432/dbname
   mysql://user:pass@host:3306/dbname
   ```

2. **Verify credentials** - Make sure username/password are correct

3. **Check network access** - Ensure your app can reach the database

### Migration Issues

If you have existing SQLite data you want to migrate:

1. **Export your current data**:
   ```bash
   sqlite3 broadcaster.db .dump > backup.sql
   ```

2. **Import to new database** (PostgreSQL example):
   ```bash
   psql $DATABASE_URL < backup.sql
   ```

## Support

If you encounter issues:

1. Run `php setup-database.php` to diagnose problems
2. Check your platform's database documentation
3. Verify environment variables are set correctly
4. Check PHP extensions (pdo_pgsql for PostgreSQL, pdo_mysql for MySQL)

## Security Notes

- Never commit database credentials to your code
- Use environment variables for all sensitive data
- Enable SSL/TLS for database connections in production
- Regularly backup your database

---

**Your data will now persist across all restarts and redeploys! 🎉**
