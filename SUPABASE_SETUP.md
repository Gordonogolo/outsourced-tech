# Supabase Setup Guide for Vercel Deployment

## Step 1: Create Supabase Account
1. Go to [supabase.com](https://supabase.com) and sign up with GitHub
2. Click "New project"
3. Fill in:
   - **Name**: outsourced-tech
   - **Database Password**: Create a strong password (remember it!)
   - **Region**: Select "Singapore" (closest to Kenya)
4. Wait ~2 minutes for setup to complete

## Step 2: Get Connection Details
1. In Supabase dashboard, go to **Settings** (gear icon) → **Database**
2. Scroll down to **Connection string**
3. Copy the **URI** (it looks like):
   ```
   postgres://postgres:[PASSWORD]@db.xxxxxx.supabase.co:5432/postgres
   ```

## Step 3: Add to Vercel
1. Go to your Vercel project → **Settings** → **Environment Variables**
2. Add these variables:

| Variable | Value |
|----------|-------|
| DB_DRIVER | pgsql |
| DB_HOST | db.xxxxxx.supabase.co (your actual host) |
| DB_PORT | 5432 |
| DB_NAME | postgres |
| DB_USER | postgres |
| DB_PASS | your-supabase-password |

## Step 4: Import Your Database
1. In Supabase dashboard, go to **SQL Editor**
2. Copy the contents from `outsourced_tech (1).sql` 
3. Paste in SQL Editor and click **Run**
4. Wait for tables to be created

## Step 5: Deploy
1. Push your code to GitHub
2. Vercel will automatically deploy
3. Your site will be live at: `yourproject.vercel.app`

## Troubleshooting
- If tables fail to create, check for MySQL-specific syntax in SQL
- Make sure your Supabase password doesn't contain special characters, or URL-encode them
- For local development, keep using your XAMPP MySQL
