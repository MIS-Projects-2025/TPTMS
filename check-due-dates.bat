@echo off
cd /d "%~dp0"
php artisan tickets:check-due
echo Due dates checked at %DATE% %TIME% >> storage\logs\due-checker.log