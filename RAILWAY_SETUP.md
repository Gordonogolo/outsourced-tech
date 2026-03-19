# Railway.com Setup Guide

## Step 1: Create Railway Account
1. Go to [railway.app](https://railway.app)
2. Click "Sign Up" → Sign up with GitHub
3. Verify your email

## Step 2: Create New Project
1. Click "New Project"
2. Select "Deploy from GitHub repo"
3. Choose "outsourced-tech" repository

## Step 3: Add Database
1. In your project, click "New" → "Database" → "PostgreSQL"
2. Wait for it to be created
3. Click on the PostgreSQL service → Copy the "Connection String"

## Step 4: Add Environment Variables
1. Click on your web service → "Variables"
2. Add these variables:

| Key | Value |
|-----|-------|
| DB_DRIVER | pgsql |
| DB_HOST | (use Railway's PostgreSQL host) |
| DB_PORT | 5432 |
| DB_NAME | (use Railway's database name) |
| DB_USER | (use Railway's username) |
| DB_PASS | (use Railway's password) |
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
1. Click "Deploy"
2. Wait ~3 minutes
3. Your site will be live at: `https://yourproject.railway.app`

## Note:
Railway also provides a free PostgreSQL database, so you can use Railway's database instead of Supabase!
