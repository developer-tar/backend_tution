@echo off
REM Run this once to stop tracking page_images (files stay on disk).
REM After running, commit the changes in GitHub Desktop.

cd /d "%~dp0"

REM If this folder is the repo root:
if exist ".git" (
  git rm -r --cached python/paper_extract/paper_extractor/page_images/ 2>nul
  if %ERRORLEVEL% EQU 0 (
    echo Done. page_images untracked. Commit in GitHub Desktop.
  ) else (
    echo Trying from parent repo...
  )
)

REM If repo root is parent (uk):
if exist "..\.git" (
  cd ..
  git rm -r --cached backend_tution/python/paper_extract/paper_extractor/page_images/ 2>nul
  if %ERRORLEVEL% EQU 0 (
    echo Done. page_images untracked. Commit in GitHub Desktop.
  )
)

pause
