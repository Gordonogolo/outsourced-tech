# Render.com Setup Guide

## Step 1: Create Render Account
1. Go to [render.com](https://render.com)
2. Click "Sign Up" → Sign up with GitHub
3. Verify your email

## Step 2: Create a New Web Service
1. In Render dashboard, click "New" → "Web Service"
2. Find your GitHub repository (outsourced-tech)
3. Click "Connect"

## Step 3: Configure the Web Service
Fill in these settings:

| Setting | Value |
|---------|-------|
| Name | outsourced-tech |
| Environment | PHP |
| Build Command | (leave empty) |
| Publish Directory | public |

## Step 4: Add Environment Variables
Scroll down to "Environment Variables" and add:

| Key | Value |
|-----|-------|
| DB_DRIVER | pgsql |
| DB_HOST | db.ueysouyhhizzrflohnby.supabase.co |
| DB_PORT | 5432 |
| DB_NAME | postgres |
| DB_USER | postgres |
| DB_PASS | Thegshow@2002 |
| APP_ENV | production |
| MAIL_DRIVER | smtp |
| MAIL_HOST | smtp.gmail.com |
| MAIL_PORT | 587 |
| MAIL_USERNAME | gordi14062002@gmail.com |
| MAIL_PASSWORD | hrkq kkfc gdfx zgey |
| MAIL_ENCRYPTION | tls |
| MAIL_FROM_ADDRESS | gordi14062002@gmail.com |
| MAIL_FROM_NAME | Outsourced Technologies |

## Step 5: Deploy
1. Click "Create Web Service"
2. Wait ~3-5 minutes for deployment
3. You'll get a free URL like: `https://outsourced-tech.onrender.com`

## Important Notes:
- Render's free tier sleeps after 15 minutes of inactivity
- It wakes up when someone visits (takes ~30 seconds)
- For always-on, you need a paid plan ($7/month)
