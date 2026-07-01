# PowerShell script to build the Society NestX Webapp Bundle
# Downloads WordPress core, installs the plugin, adds setup.php, and compresses it.

$ErrorActionPreference = "Stop"

$WORKSPACE = Split-Path -Parent $MyInvocation.MyCommand.Path
$ZIP_URL = "https://wordpress.org/latest.zip"
$ZIP_FILE = Join-Path $WORKSPACE "wordpress-temp.zip"
$DIST_DIR = Join-Path $WORKSPACE "dist"
$OUT_ZIP = Join-Path $WORKSPACE "society-nestx-webapp.zip"

Write-Host "==============================================" -ForegroundColor Cyan
Write-Host "  SocietyNestX - Webapp Packager  " -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan

# 1. Clean up old build files
if (Test-Path $ZIP_FILE) {
    Write-Host "[1/7] Cleaning old temp zip..." -ForegroundColor Yellow
    Remove-Item $ZIP_FILE -Force
}
if (Test-Path $DIST_DIR) {
    Write-Host "[1/7] Cleaning old dist folder..." -ForegroundColor Yellow
    Remove-Item $DIST_DIR -Recurse -Force
}
if (Test-Path $OUT_ZIP) {
    Write-Host "[1/7] Cleaning old webapp bundle zip..." -ForegroundColor Yellow
    Remove-Item $OUT_ZIP -Force
}

# 2. Download WordPress Core
Write-Host "[2/7] Downloading WordPress Core (latest.zip)..." -ForegroundColor Green
Invoke-WebRequest -Uri $ZIP_URL -OutFile $ZIP_FILE

# 3. Extract WordPress Core
Write-Host "[3/7] Extracting WordPress Core..." -ForegroundColor Green
Expand-Archive -Path $ZIP_FILE -DestinationPath $WORKSPACE

# Rename extracted folder to dist
Rename-Item -Path (Join-Path $WORKSPACE "wordpress") -NewName "dist"
Start-Sleep -Seconds 1

# 4. Create plugin directory structure
Write-Host "[4/7] Creating plugin folder structure..." -ForegroundColor Green
$PLUGIN_DIR = Join-Path $DIST_DIR "wp-content\plugins\society-nestx"
New-Item -ItemType Directory -Force -Path $PLUGIN_DIR | Out-Null

# 5. Copy plugin assets & files from src/
Write-Host "[5/7] Copying plugin code & assets from src..." -ForegroundColor Green
Copy-Item -Path (Join-Path $WORKSPACE "src\*") -Destination $PLUGIN_DIR -Recurse -Force

# 6. Copy setup.php to root
Write-Host "[6/7] Copying setup.php wizard to root..." -ForegroundColor Green
Copy-Item -Path (Join-Path $WORKSPACE "setup.php") -Destination (Join-Path $DIST_DIR "setup.php") -Force

# 7. Package everything into webapp zip
Write-Host "[7/7] Packaging into society-nestx-webapp.zip..." -ForegroundColor Green
Get-ChildItem -Path $DIST_DIR | Compress-Archive -DestinationPath $OUT_ZIP -Force

# 8. Clean up temp files
Write-Host "Cleaning up temporary files..." -ForegroundColor Gray
Remove-Item $ZIP_FILE -Force
Remove-Item $DIST_DIR -Recurse -Force

Write-Host "==============================================" -ForegroundColor Cyan
Write-Host "  Success! Webapp bundle created at:  " -ForegroundColor Cyan
Write-Host "  $OUT_ZIP" -ForegroundColor White
Write-Host "==============================================" -ForegroundColor Cyan
