Set-Location -Path $env:TEMP
if (Test-Path frontend) { Remove-Item -Recurse -Force frontend }
npx -y create-vite@latest frontend --template react
Set-Location frontend
npm install
npm install react-router-dom axios
Set-Location ..
Move-Item -Path frontend -Destination "\\wsl.localhost\Ubuntu\home\robert\galotxas\" -Force
