"""
Configuration for FastAPI backend.
"""

import os
from pathlib import Path

# API Key (fallback if environment variable not set)
DEFAULT_GOOGLE_API_KEY = ""

# Gemini Model
DEFAULT_GEMINI_MODEL = "models/gemini-2.5-flash"

# Processing Settings
DEFAULT_DPI = 200
DEFAULT_MAX_RETRIES = 5
DEFAULT_RETRY_DELAY = 2
DEFAULT_MAX_WORKERS = 4

# Poppler Path
POPPLER_PATH = None

# CORS Configuration
CORS_ORIGINS = [
    "http://localhost:3000",
]

# File Upload Settings
UPLOAD_DIR = Path("backend_api/uploads")
UPLOAD_DIR.mkdir(parents=True, exist_ok=True)

MAX_FILE_SIZE = 10 * 1024 * 1024  # 10MB
ALLOWED_EXTENSIONS = {".pdf"}

# Extraction Settings
EXTRACTION_OUTPUT_DIR = "paper_extractor/output"
DIAGRAMS_DIR = "paper_extractor/diagrams"
PAGE_IMAGES_DIR = "paper_extractor/page_images"

# API Settings
API_HOST = "0.0.0.0"
API_PORT = 8000
DEBUG = True

