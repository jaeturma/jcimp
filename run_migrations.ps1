#!/usr/bin/env powershell

# ============================================================
#  Laravel Migration Execution Script for PowerShell
# ============================================================
#  This script runs database migrations for the OTP and OCR system
# ============================================================

$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  Concert Ticket System - Database Migration" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# Change to script directory
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptDir

Write-Host "[1/4] Checking environment..." -ForegroundColor Yellow

# Check if artisan file exists
if (-not (Test-Path "artisan")) {
    Write-Host "ERROR: artisan file not found in $(Get-Location)" -ForegroundColor Red
    Write-Host "Please run this script from the Laravel project root directory" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

# Check if .env exists
if (-not (Test-Path ".env")) {
    Write-Host "ERROR: .env file not found" -ForegroundColor Red
    Write-Host "Please create .env file before running migrations" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host "[✓] Environment OK - $(Get-Location)" -ForegroundColor Green
Write-Host ""

# Check if PHP is available
Write-Host "[2/4] Checking PHP availability..." -ForegroundColor Yellow
try {
    $phpVersion = php -v 2>&1
    Write-Host "[✓] PHP available: $($phpVersion[0])" -ForegroundColor Green
} catch {
    Write-Host "ERROR: PHP not found in PATH" -ForegroundColor Red
    Write-Host "Please add PHP to your system PATH" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host ""
Write-Host "[3/4] Installing/updating Composer dependencies..." -ForegroundColor Yellow
try {
    & composer install 2>&1 | ForEach-Object { Write-Host $_ }
    Write-Host "[✓] Composer dependencies installed" -ForegroundColor Green
} catch {
    Write-Host "ERROR: Composer install failed" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host ""
Write-Host "[4/4] Running database migrations..." -ForegroundColor Yellow
Write-Host ""

try {
    Write-Host "Executing: php artisan migrate" -ForegroundColor Cyan
    & php artisan migrate 2>&1 | ForEach-Object { Write-Host $_ }
    
    if ($LASTEXITCODE -ne 0) {
        Write-Host ""
        Write-Host "Trying with --force flag..." -ForegroundColor Yellow
        & php artisan migrate --force 2>&1 | ForEach-Object { Write-Host $_ }
    }
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "============================================================" -ForegroundColor Green
        Write-Host "[✓] Migrations completed successfully!" -ForegroundColor Green
        Write-Host "============================================================" -ForegroundColor Green
        Write-Host ""
        
        Write-Host "Verifying migration status..." -ForegroundColor Cyan
        & php artisan migrate:status 2>&1 | ForEach-Object { Write-Host $_ }
    } else {
        Write-Host ""
        Write-Host "ERROR: Migrations failed" -ForegroundColor Red
        Write-Host "Check error messages above for details" -ForegroundColor Red
    }
} catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "Next Steps:" -ForegroundColor Cyan
Write-Host "1. Install Tesseract OCR system binary:" -ForegroundColor White
Write-Host "   - Windows: https://github.com/UB-Mannheim/tesseract/wiki" -ForegroundColor Gray
Write-Host "   - Linux: sudo apt-get install tesseract-ocr tesseract-ocr-fil" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Verify OCR service: php artisan tinker" -ForegroundColor White
Write-Host "   >> (new App\Services\PaymentProofOcrService)::isAvailable()" -ForegroundColor Gray
Write-Host ""
Write-Host "3. Update frontend components to use OTP and payment upload" -ForegroundColor White
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

Read-Host "Press Enter to exit"
