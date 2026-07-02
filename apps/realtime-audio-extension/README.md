# OPAS Realtime Audio Extension

Small Chromium extension bridge for the OPAS realtime audio web screen.

The popup is intentionally simple:

- `Open Web`: opens `http://127.0.0.1:5010/realtime-audio/ui`
- `Refresh`: reinjects the local web bridge if the web page was already open

All real work happens in the web screen: choose tab, connect, stop, view transcript, and prepare translation display.

## Run

1. Start the local audio/STT service:

   ```bash
   scripts/start-realtime-audio-capture.sh
   ```

2. Load this directory as an unpacked extension:

   ```text
   apps/realtime-audio-extension
   ```

3. Click the extension icon.
4. Press `Open Web`.
5. In the web screen, choose the tab and press `Connect`.

## Multiple Chrome Windows Or Profiles

If you have two Google Chrome apps/profiles open, the web screen only sees tabs from the Chrome profile where this extension is loaded. Load/reload the extension in the same profile that contains the tab you want to capture.

## Notes

- Internal browser pages such as `chrome://extensions` cannot be captured.
- Chrome may require one extra click on the extension icon for the selected target tab before capture starts.
- The local backend must be running before `Connect`; otherwise start `scripts/start-realtime-audio-capture.sh`.
