# Bible Brain Downloader

A small desktop program (Windows, macOS, Linux). Choose a country and language, then download a Bible Brain
fileset (text or audio) as one ZIP file. Uses only the Python standard library.

## Run it
    python main.py

Python 3.8 or newer with Tkinter (included with the python.org installers).

## Command line mode
Handy for scripts, servers and computers without a desktop. Give any option and the window is skipped:

    export BIBLE_BRAIN_KEY=your-key                       # or use --key
    python main.py --list-countries
    python main.py --list-filesets --country IN --language hin
    python main.py --fileset HINDOVN2DA --type audio_drama --out ./downloads

Add `--all-types` to `--list-filesets` to include streaming and video filesets. Run `python main.py --help` for everything.

## Make a Windows program (.exe)
Double-click `build_exe.bat`, or run:

    pyinstaller --onefile --noconsole --icon=icon.ico main.py

The program appears in the `dist` folder. Share that single file.

## Use it
1. Request a free API key at https://4.dbt.io/api_key/request (it arrives by email).
2. Paste it in the box and press **Load countries and languages**.
3. Choose a country, then a language. Tick the types you want: USX/USFM text, other text, audio.
4. Press **Download** next to a fileset, or type a fileset ID yourself and press **Download from Bible Brain**.
5. The ZIP is saved in the folder you choose. It contains the files and a README with the copyright line.

## Notes
- Audio and USX/USFM files are fetched from the files Bible Brain offers for download.
  Plain text, formatted text and JSON are assembled chapter by chapter into one file per book (this can take a few minutes).
- Streaming-only filesets (HLS audio and video) cannot be downloaded and are listed under "Not shown".
- Check the licence and copyright of each Bible version before you use or share it.
- "Remember key" stores the key in plain text in `~/.bible_brain_downloader.json`. Leave it unticked on shared computers.

## About and links
[Bible Brain](https://www.faithcomesbyhearing.com/audio-bible-resources/bible-brain) |
[Developer documentation](https://www.faithcomesbyhearing.com/bible-brain/developer-documentation) |
[API reference](https://www.faithcomesbyhearing.com/bible-brain/api-reference) |
[Request a free API key](https://4.dbt.io/api_key/request) |
[License](https://www.faithcomesbyhearing.com/bible-brain/license) |
[Terms](https://www.faithcomesbyhearing.com/bible-brain/terms-conditions) |
[Copyright policy](https://www.faithcomesbyhearing.com/copyright-policy)

Developed by Shalom Design S2dio (SDS), with the help of AI tools. This is an unofficial community project and is not published or supported by Faith Comes By Hearing. Scripture content is provided by [Bible Brain](https://www.faithcomesbyhearing.com/audio-bible-resources/bible-brain), a project of [Faith Comes By Hearing](https://www.faithcomesbyhearing.com/). Bible Brain support: support@digitalbibleplatform.com
