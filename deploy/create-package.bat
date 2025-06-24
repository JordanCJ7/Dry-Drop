@echo off
echo ====================================
echo    Dry-Drop Deployment Helper
echo ====================================
echo.

:: Create deployment folder
if not exist "deploy-package" mkdir deploy-package

:: Go to parent directory to copy files
cd ..

echo Copying project files...

:: Copy PHP files from root
copy *.php deploy\deploy-package\ >nul 2>&1

:: Copy directories
xcopy /E /I /Y admin deploy\deploy-package\admin\ >nul 2>&1
xcopy /E /I /Y customer deploy\deploy-package\customer\ >nul 2>&1 
xcopy /E /I /Y includes deploy\deploy-package\includes\ >nul 2>&1
xcopy /E /I /Y assets deploy\deploy-package\assets\ >nul 2>&1

:: Copy other files
if exist LICENSE copy LICENSE deploy\deploy-package\ >nul 2>&1
if exist README.md copy README.md deploy\deploy-package\ >nul 2>&1

echo Files copied successfully!
echo.

:: Create deployment instructions
cd deploy
(
echo DEPLOYMENT INSTRUCTIONS FOR DRY-DROP
echo =====================================
echo.
echo 1. UPLOAD FILES:
echo    - Upload all files in 'deploy-package' folder to your hosting's public_html
echo    - Make sure file permissions are set correctly ^(755 for folders, 644 for files^)
echo.
echo 2. DATABASE SETUP:
echo    - Create a new MySQL database in your hosting control panel
echo    - Note down: Database Name, Username, Password, Host
echo    - Import your database using the SQL file ^(if you have existing data^)
echo.
echo 3. CONFIGURATION:
echo    - Your config.php will automatically detect InfinityFree
echo    - BUT you need to update the InfinityFree section with your actual details:
echo      define^('DB_HOST', 'your_actual_host'^);
echo      define^('DB_USER', 'your_actual_username'^);
echo      define^('DB_PASS', 'your_actual_password'^);
echo      define^('DB_NAME', 'your_actual_database'^);
echo.
echo 4. TEST YOUR SITE:
echo    - Visit your domain
echo    - Test login/registration
echo    - Check all functionality
echo.
echo 5. EXPORT DATABASE:
echo    - Visit: http://localhost/Dry-Drop/deploy/export_database.php
echo    - This will download your current database backup
echo.
echo For support, refer to your hosting provider's documentation.
) > deploy-package\DEPLOYMENT_INSTRUCTIONS.txt

echo ====================================
echo Deployment package created!
echo ====================================
echo.
echo Next steps:
echo 1. Check the 'deploy-package' folder
echo 2. Read DEPLOYMENT_INSTRUCTIONS.txt
echo 3. Export your database first!
echo 4. Sign up for InfinityFree hosting
echo.
pause
