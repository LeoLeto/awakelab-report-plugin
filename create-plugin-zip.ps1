# PowerShell script to create a Moodle plugin ZIP file
# Run this script from the plugin root directory

$pluginName = "questionbank"
$zipFileName = "report_questionbank.zip"
$tempDir = "temp_plugin_build"

Write-Host "Creating Moodle plugin ZIP file..." -ForegroundColor Green

# Remove old zip and temp directory if they exist
if (Test-Path $zipFileName) {
    Remove-Item $zipFileName -Force
    Write-Host "Removed old ZIP file" -ForegroundColor Yellow
}

if (Test-Path $tempDir) {
    Remove-Item $tempDir -Recurse -Force
}

# Create temporary directory
New-Item -ItemType Directory -Path "$tempDir\$pluginName" -Force | Out-Null

# Copy all plugin files to temp directory
$excludeItems = @($zipFileName, "create-plugin-zip.ps1", $tempDir, ".git")

Get-ChildItem -Path . | Where-Object { 
    $excludeItems -notcontains $_.Name
} | ForEach-Object {
    Copy-Item -Path $_.FullName -Destination "$tempDir\$pluginName\" -Recurse -Force
}

# Create ZIP from the temp directory
Set-Location $tempDir
Compress-Archive -Path $pluginName -DestinationPath "..\$zipFileName" -CompressionLevel Optimal
Set-Location ..

# Clean up
Remove-Item $tempDir -Recurse -Force

Write-Host ""
Write-Host "ZIP file created: $zipFileName" -ForegroundColor Green
Write-Host ""
Write-Host "Installation steps:" -ForegroundColor Cyan
Write-Host "1. Log in to Moodle as administrator"
Write-Host "2. Go to: Site administration -> Plugins -> Install plugins"
Write-Host "3. Upload the ZIP file"
Write-Host "4. Click Install plugin from the ZIP file"
Write-Host "5. Verify plugin type shows as Course report"
Write-Host "6. Complete the installation"
Write-Host ""
