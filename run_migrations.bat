@echo off
REM ============================================================
REM  Laravel Migration Execution Script for Windows
REM ============================================================
REM  This script runs database migrations for the OTP and OCR system
REM ============================================================

setlocal enabledelayedexpansion

echo.
echo ============================================================
echo  Concert Ticket System - Database Migration
echo ============================================================
echo.

REM Change to project directory
cd /d "%~dp0"

REM Check if artisan file exists
if not exist artisan (
    echo ERROR: artisan file not found in %cd%
    echo Please run this script from the Laravel project root directory
    pause
    exit /b 1
)

echo [1/3] Checking environment...
if not exist .env (
    echo ERROR: .env file not found
    echo Please create .env file before running migrations
    pause
    exit /b 1
)

echo [✓] Environment configured

echo.
echo [2/3] Installing/updating Composer dependencies...
call composer install
if errorlevel 1 (
    echo ERROR: Composer install failed
    pause
    exit /b 1
)
echo [✓] Composer dependencies installed

echo.
echo [3/3] Running database migrations...
echo.
echo Running: php artisan migrate
echo.
call php artisan migrate
if errorlevel 1 (
    echo ERROR: Migrations failed
    echo.
    echo Trying with --force flag...
    call php artisan migrate --force
    if errorlevel 1 (
        echo CRITICAL ERROR: Migrations could not be executed
        pause
        exit /b 1
    )
)

echo.
echo ============================================================
echo [✓] Migrations completed successfully!
echo ============================================================
echo.

REM Verify migrations
echo Verifying migration status...
call php artisan migrate:status

echo.
echo ============================================================
echo Next Steps:
echo 1. Install Tesseract OCR system binary:
echo    - Windows: https://github.com/UB-Mannheim/tesseract/wiki
echo    - Linux: sudo apt-get install tesseract-ocr tesseract-ocr-fil
echo.
echo 2. Verify OCR service available: php artisan tinker
echo    >> (new App\Services\PaymentProofOcrService)::isAvailable()
echo.
echo 3. Update frontend components to use OTP and payment upload
echo============================================================
echo.

pause
