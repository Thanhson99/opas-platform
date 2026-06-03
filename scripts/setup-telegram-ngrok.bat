@echo off
setlocal
REM Windows launcher: delegate all arguments to the PowerShell implementation.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-telegram-ngrok.ps1" %*
endlocal
