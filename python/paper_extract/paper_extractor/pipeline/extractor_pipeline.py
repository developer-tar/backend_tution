"""
Main extraction pipeline orchestrating all services.
Uses LangChain LCEL for pipeline composition with parallel processing.
"""

import logging
import uuid
from datetime import datetime
from pathlib import Path
from typing import List, Dict, Any, Optional
from collections import defaultdict
from concurrent.futures import ThreadPoolExecutor, as_completed

logger = logging.getLogger(__name__)


class PaperExtractorPipeline:
    """
    Main pipeline for extracting questions, diagrams, and equations from PDF question papers.
    """
    
    def __init__(self, api_key: str, model: str = "gemini-1.5-pro", dpi: int = 250,
                 output_dir: str = "paper_extractor/output",
                 page_images_dir: str = "paper_extractor/page_images",
                 diagrams_dir: str = "paper_extractor/diagrams",
                 max_workers: int = 4):
        """
        Initialize extraction pipeline.
        
        Args:
            api_key: Google API key for Gemini
            model: Gemini model name
            dpi: Image DPI for PDF conversion
            output_dir: Directory for final output
            page_images_dir: Directory for page images
            diagrams_dir: Directory for extracted diagrams
            max_workers: Maximum parallel workers for OCR
        """
        self.api_key = api_key
        self.model = model
        self.dpi = dpi
        self.max_workers = max_workers
        self.output_dir = Path(output_dir)
        self.page_images_dir = Path(page_images_dir)
        self.diagrams_dir = Path(diagrams_dir)
        
        self.output_dir.mkdir(parents=True, exist_ok=True)
        self.page_images_dir.mkdir(parents=True, exist_ok=True)
        self.diagrams_dir.mkdir(parents=True, exist_ok=True)
        
        try:
            from paper_extractor.services.pdf_to_image import PDFToImageConverter
            from paper_extractor.services.gemini_ocr import GeminiOCRService
            from paper_extractor.services.diagram_cropper import DiagramCropper
            from paper_extractor.services.validation import ExtractionValidator
            
            self.pdf_converter = PDFToImageConverter(dpi=dpi, output_dir=str(self.page_images_dir))
            self.ocr_service = GeminiOCRService(api_key=api_key, model=model)
            self.diagram_cropper = DiagramCropper(output_dir=str(self.diagrams_dir))
            self.validator = ExtractionValidator()
        except ImportError as e:
            logger.error(f"Failed to import required services: {e}")
            self.pdf_converter = None
            self.ocr_service = None
            self.diagram_cropper = None
            self.validator = None
        except Exception as e:
            logger.error(f"Failed to initialize services: {e}")
            self.pdf_converter = None
            self.ocr_service = None
            self.diagram_cropper = None
            self.validator = None
    
    def run(self, pdf_path: str, validate: bool = True) -> Dict[str, Any]:
        """
        Run the extraction pipeline.
        
        Args:
            pdf_path: Path to PDF file
            validate: Whether to validate extraction results
            
        Returns:
            Dictionary with extraction results
        """
        if not self.pdf_converter or not self.ocr_service:
            logger.error("Required services not initialized")
            return {
                "source_pdf": Path(pdf_path).name,
                "total_pages": 0,
                "questions": [],
                "extraction_metadata": {
                    "dpi": self.dpi,
                    "model": self.model,
                    "timestamp": datetime.now().isoformat(),
                    "total_questions": 0,
                    "total_diagrams": 0
                },
                "validation": {
                    "valid": False,
                    "total_errors": 1,
                    "total_warnings": 0,
                    "errors": ["Required services not initialized. Please check dependencies and API configuration."],
                    "warnings": []
                }
            }
        
        try:
            logger.info(f"Starting extraction for: {pdf_path}")
            
            page_data = self.pdf_converter.convert(pdf_path, clean_output=True)
            total_pages = len(page_data)
            
            logger.info(f"Converted {total_pages} pages to images")
            
            all_questions = []
            
            with ThreadPoolExecutor(max_workers=self.max_workers) as executor:
                futures = {
                    executor.submit(self.ocr_service.extract_text_from_image, 
                                  page_info["image_path"], page_info["page_number"]): page_info
                    for page_info in page_data
                }
                
                for future in as_completed(futures):
                    page_info = futures[future]
                    try:
                        ocr_result = future.result()
                        if "error" in ocr_result:
                            logger.warning(f"OCR failed for page {page_info['page_number']}: {ocr_result['error']}")
                            continue
                        all_questions.extend(ocr_result.get("questions", []))
                    except Exception as e:
                        logger.error(f"Error processing page {page_info['page_number']}: {e}")
            
            result = {
                "source_pdf": Path(pdf_path).name,
                "total_pages": total_pages,
                "questions": all_questions,
                "extraction_metadata": {
                    "dpi": self.dpi,
                    "model": self.model,
                    "timestamp": datetime.now().isoformat(),
                    "total_questions": len(all_questions),
                    "total_diagrams": 0
                }
            }
            
            if validate and self.validator:
                validation_result = self.validator.validate(result)
                result["validation"] = validation_result.to_dict()
            else:
                result["validation"] = {
                    "valid": True,
                    "total_errors": 0,
                    "total_warnings": 0,
                    "errors": [],
                    "warnings": []
                }
            
            return result
            
        except Exception as e:
            logger.error(f"Pipeline execution failed: {e}")
            import traceback
            logger.error(traceback.format_exc())
            
            return {
                "source_pdf": Path(pdf_path).name,
                "total_pages": 0,
                "questions": [],
                "extraction_metadata": {
                    "dpi": self.dpi,
                    "model": self.model,
                    "timestamp": datetime.now().isoformat(),
                    "total_questions": 0,
                    "total_diagrams": 0
                },
                "validation": {
                    "valid": False,
                    "total_errors": 1,
                    "total_warnings": 0,
                    "errors": [f"Pipeline execution failed: {str(e)}"],
                    "warnings": []
                }
            }

