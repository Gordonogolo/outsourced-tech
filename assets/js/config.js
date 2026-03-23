// Configuration for the application
const BASE_URL = 'http://192.168.1.143/outsourced/public/';

// For API calls - relative path
const API_URL = '../api/v1/';

const CONFIG = {
    SUPABASE_URL: 'https://xajtokukmeeyfgditwns.supabase.co',
    SUPABASE_ANON_KEY: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhhanRva3VrbWVleWZnZGl0d25zIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzA4OTQ1NjQsImV4cCI6MjA4NjQ3MDU2NH0.fIZLwSHiSBNCDoWjv0qE4AgDTmV5KyE1SBYfH4i1pks',
    API_BASE_URL: '/api'
};

if (typeof window !== 'undefined') {
    window.BASE_URL = BASE_URL;
    window.CONFIG = CONFIG;
}
