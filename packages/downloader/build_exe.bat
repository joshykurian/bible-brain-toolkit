@echo off
REM Builds BibleBrainDownloader.exe (run from this folder, ideally in an Anaconda Prompt)
python -m pip install --upgrade pyinstaller
if exist icon.ico (
  pyinstaller --onefile --noconsole --name BibleBrainDownloader --icon=icon.ico main.py
) else (
  pyinstaller --onefile --noconsole --name BibleBrainDownloader main.py
)
echo.
echo Done. Your program is in the "dist" folder.
pause
