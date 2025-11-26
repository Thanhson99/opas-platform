@echo off
REM =========================================================
REM  launch-browser-connector.bat
REM  Windows version of launch-browser-connector.sh
REM  Launches a separate browser instance with DevTools
REM =========================================================

setlocal enabledelayedexpansion

REM -------- Default configuration --------------------------
set "BROWSER=chrome"                          REM Supported: chrome, edge, brave
set "PORT=9222"                               REM Remote debugging port
set "USE_SYSTEM_PROFILE=0"                    REM 1 = use default Chrome profile
set "HEADLESS=0"                               REM 1 = launch in headless mode
set "EXTRA_FLAGS="                             REM Any extra flags to pass to browser
set "PROFILE_DIR=%USERPROFILE%\.puppetbind\profiles"
set "USER_DATA_DIR=%PROFILE_DIR%\%BROWSER%"

REM -------- Parse command line arguments -------------------
:parse_args
if "%~1"=="" goto after_parse
if /I "%~1"=="--port" (
    set "PORT=%~2"
    shift
) else if /I "%~1"=="--browser" (
    set "BROWSER=%~2"
    shift
) else if /I "%~1"=="--use-system-profile" (
    set "USE_SYSTEM_PROFILE=1"
) else if /I "%~1"=="--headless" (
    set "HEADLESS=1"
) else if /I "%~1"=="--extra" (
    set "EXTRA_FLAGS=%~2"
    shift
) else (
    echo [WARN] Unknown option: %~1
)
shift
goto parse_args
:after_parse

REM -------- Locate browser executable ----------------------
if /I "%BROWSER%"=="chrome" (
    set "BROWSER_EXE=C:\Program Files\Google\Chrome\Application\chrome.exe"
    if not exist "!BROWSER_EXE!" set "BROWSER_EXE=C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"
) else if /I "%BROWSER%"=="edge" (
    set "BROWSER_EXE=C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"
) else if /I "%BROWSER%"=="brave" (
    set "BROWSER_EXE=C:\Program Files\BraveSoftware\Brave-Browser\Application\brave.exe"
) else (
    echo [ERROR] Unsupported browser: %BROWSER%
    exit /b 1
)

if not exist "!BROWSER_EXE!" (
    echo [ERROR] Browser executable not found: !BROWSER_EXE!
    exit /b 1
)

REM -------- Show configuration -----------------------------
echo ========================================
echo Using browser: %BROWSER%
echo Executable: !BROWSER_EXE!
if "%USE_SYSTEM_PROFILE%"=="1" (
    echo User data dir: [System default profile]
) else (
    echo User data dir (automation): %USER_DATA_DIR%
)
echo Binding on port: %PORT%
if not "%EXTRA_FLAGS%"=="" echo Extra flags: %EXTRA_FLAGS%
echo ========================================

REM -------- Prepare user data directory --------------------
if "%USE_SYSTEM_PROFILE%"=="0" (
    if not exist "%USER_DATA_DIR%" (
        echo Creating profile directory: %USER_DATA_DIR%
        mkdir "%USER_DATA_DIR%"
    )
)

REM -------- Kill any running instance on same profile ------
REM Note: Optional - uncomment if needed
REM taskkill /IM chrome.exe /F >nul 2>&1

REM -------- Build browser flags ----------------------------
set "FLAGS=--remote-debugging-port=%PORT% --no-first-run --no-default-browser-check --disable-extensions"
if "%USE_SYSTEM_PROFILE%"=="0" set "FLAGS=%FLAGS% --user-data-dir=%USER_DATA_DIR%"
if "%HEADLESS%"=="1" set "FLAGS=%FLAGS% --headless --disable-gpu"
if not "%EXTRA_FLAGS%"=="" set "FLAGS=%FLAGS% %EXTRA_FLAGS%"

REM -------- Launch browser ----------------------------------
echo.
echo Launching a separate instance...
start "" "!BROWSER_EXE!" %FLAGS%
if errorlevel 1 (
    echo [ERROR] Failed to launch browser.
    exit /b 1
)

echo Launched successfully.
echo Waiting for DevTools endpoint on :%PORT% ...
echo Open: http://localhost:%PORT%/json/version
echo.

REM -------- Simple wait loop (check endpoint) --------------
REM Requires PowerShell for Invoke-WebRequest
for /L %%i in (1,1,20) do (
    powershell -Command ^
      "try { $r = Invoke-WebRequest -Uri http://localhost:%PORT%/json/version -UseBasicParsing -TimeoutSec 2; if($r.StatusCode -eq 200){ exit 0 } } catch { exit 1 }" >nul 2>&1
    if !errorlevel! == 0 (
        echo [OK] DevTools endpoint available.
        goto end
    )
    timeout /t 1 >nul
    echo Waiting...
)

echo [WARN] DevTools endpoint not available on :%PORT%.
echo Check if Chrome closed automatically or flags not applied.

:end
pause
endlocal
