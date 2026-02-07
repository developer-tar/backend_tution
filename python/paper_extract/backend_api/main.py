"""
FastAPI backend for PDF Question Paper Extraction.
"""

import os
import uuid
import logging
from pathlib import Path
from typing import Optional

from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from fastapi.responses import JSONResponse
from dotenv import load_dotenv

from backend_api.config import (
    CORS_ORIGINS, UPLOAD_DIR, MAX_FILE_SIZE, ALLOWED_EXTENSIONS,
    EXTRACTION_OUTPUT_DIR, DIAGRAMS_DIR, PAGE_IMAGES_DIR, 
    DEFAULT_GOOGLE_API_KEY, DEFAULT_GEMINI_MODEL,
    DEFAULT_DPI, DEFAULT_MAX_WORKERS
)
from backend_api.models import ExtractionResponse, ErrorResponse

import sys
sys.path.append(str(Path(__file__).parent.parent))
from paper_extractor.pipeline.extractor_pipeline import PaperExtractorPipeline

project_root = Path(__file__).parent.parent
env_path = project_root / ".env"
load_dotenv(dotenv_path=env_path)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

api_key_on_startup = os.getenv("GOOGLE_API_KEY") or DEFAULT_GOOGLE_API_KEY
if api_key_on_startup:
    source = "environment" if os.getenv("GOOGLE_API_KEY") else "config fallback"
    logger.info(f"API Key loaded from {source}: {api_key_on_startup[:20]}...")
else:
    logger.error("GOOGLE_API_KEY not found in environment or config!")

app = FastAPI(
    title="PDF Question Paper Extractor API",
    description="Extract questions, diagrams, and equations from PDF question papers",
    version="1.0.0"
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

diagrams_path = Path(DIAGRAMS_DIR)
diagrams_path.mkdir(parents=True, exist_ok=True)
app.mount("/diagrams", StaticFiles(directory=str(diagrams_path)), name="diagrams")

page_images_path = Path(PAGE_IMAGES_DIR)
page_images_path.mkdir(parents=True, exist_ok=True)
app.mount("/page_images", StaticFiles(directory=str(page_images_path)), name="page_images")


def validate_pdf_file(file: UploadFile) -> None:
    """Validate uploaded PDF file."""
    if not file.filename:
        raise HTTPException(status_code=400, detail="No filename provided")
    
    file_ext = Path(file.filename).suffix.lower()
    if file_ext not in ALLOWED_EXTENSIONS:
        raise HTTPException(
            status_code=400,
            detail=f"Invalid file type. Only PDF files are allowed. Got: {file_ext}"
        )
    
    if file.content_type and not file.content_type.startswith('application/pdf'):
        raise HTTPException(
            status_code=400,
            detail=f"Invalid content type. Expected application/pdf, got: {file.content_type}"
        )


@app.get("/")
async def root():
    """Root endpoint."""
    return {
        "message": "PDF Question Paper Extractor API",
        "version": "1.0.0",
        "endpoints": {
            "extract": "/api/extract",
            "health": "/health"
        }
    }


@app.get("/health")
async def health_check():
    """Health check endpoint."""
    return {"status": "healthy", "service": "pdf-extractor-api"}


@app.post("/api/extract", response_model=ExtractionResponse)
async def extract_pdf(file: UploadFile = File(...)):
    """
    Extract questions, diagrams, and equations from PDF.
    """
    extraction_id = str(uuid.uuid4())
    temp_pdf_path = None
    
    try:
        logger.info(f"Received extraction request: {file.filename}")
        
        validate_pdf_file(file)
        
        content = await file.read()
        file_size = len(content)
        
        if file_size > MAX_FILE_SIZE:
            raise HTTPException(
                status_code=413,
                detail=f"File too large. Maximum size: {MAX_FILE_SIZE / 1024 / 1024:.1f}MB"
            )
        
        logger.info(f"File size: {file_size / 1024:.1f}KB")
        
        temp_pdf_path = UPLOAD_DIR / f"{extraction_id}_{file.filename}"
        with open(temp_pdf_path, "wb") as f:
            f.write(content)
        
        logger.info(f"Saved temporary file: {temp_pdf_path}")
        
        api_key = os.getenv("GOOGLE_API_KEY") or DEFAULT_GOOGLE_API_KEY
        logger.info(f"API Key loaded: {api_key[:20] if api_key else 'None'}...")
        if not api_key:
            logger.error("GOOGLE_API_KEY not found in environment or config")
            raise HTTPException(
                status_code=500,
                detail="GOOGLE_API_KEY not configured on server"
            )
        
        model = os.getenv("GEMINI_MODEL") or DEFAULT_GEMINI_MODEL
        dpi = int(os.getenv("DPI", str(DEFAULT_DPI)))
        max_workers = int(os.getenv("MAX_WORKERS", str(DEFAULT_MAX_WORKERS)))
        
        logger.info(f"Initializing pipeline: model={model}, dpi={dpi}, max_workers={max_workers}")
        
        pipeline = PaperExtractorPipeline(
            api_key=api_key,
            model=model,
            dpi=dpi,
            output_dir=EXTRACTION_OUTPUT_DIR,
            page_images_dir=PAGE_IMAGES_DIR,
            diagrams_dir=DIAGRAMS_DIR,
            max_workers=max_workers
        )
        
        logger.info("Running extraction pipeline...")
        result = pipeline.run(str(temp_pdf_path), validate=True)
        
        logger.info("Extraction complete")
        
        def fix_diagram_paths(data):
            """Convert full paths to URL paths for frontend"""
            if isinstance(data, dict):
                for key, value in data.items():
                    if key == "image_path" and isinstance(value, str):
                        if "diagrams" in value:
                            filename = Path(value).name
                            data[key] = f"/diagrams/{filename}"
                    elif key == "diagrams" and isinstance(value, list):
                        for diagram in value:
                            fix_diagram_paths(diagram)
                    elif isinstance(value, (dict, list)):
                        fix_diagram_paths(value)
            elif isinstance(data, list):
                for item in data:
                    fix_diagram_paths(item)
            return data
        
        result = fix_diagram_paths(result)
        
        response_data = {
            "success": True,
            "extraction_id": extraction_id,
            "source_pdf": file.filename,
            "total_pages": result["total_pages"],
            "questions": result["questions"],
            "extraction_metadata": result["extraction_metadata"],
            "validation": result.get("validation")
        }
        
        return JSONResponse(content=response_data)
        
    except HTTPException:
        raise
    
    except Exception as e:
        import traceback
        error_details = traceback.format_exc()
        logger.error(f"Extraction failed: {e}")
        logger.error(f"Full traceback:\n{error_details}")
        
        error_response = {
            "success": False,
            "extraction_id": extraction_id,
            "source_pdf": file.filename if file.filename else "unknown",
            "total_pages": 0,
            "questions": [],
            "extraction_metadata": {
                "dpi": 0,
                "model": "",
                "timestamp": "",
                "total_questions": 0,
                "total_diagrams": 0
            },
            "error": str(e)
        }
        
        return JSONResponse(content=error_response, status_code=500)
    
    finally:
        if temp_pdf_path and temp_pdf_path.exists():
            try:
                temp_pdf_path.unlink()
                logger.info(f"Cleaned up temporary file: {temp_pdf_path}")
            except Exception as e:
                logger.warning(f"Failed to cleanup temp file: {e}")

