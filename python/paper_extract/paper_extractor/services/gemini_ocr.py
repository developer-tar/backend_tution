"""
Gemini OCR service with LangChain integration.
Uses Google's Gemini Vision API for extracting text, equations, and diagrams from images.
"""

import base64
import json
import logging
import time
from pathlib import Path
from typing import Dict, Any, List, Optional
import re
from PIL import Image

logger = logging.getLogger(__name__)


class GeminiOCRService:
    """OCR service using Gemini Vision API with LangChain."""
    
    def __init__(self, api_key: str, model: str = "gemini-1.5-pro",
                 prompt_file: str = "paper_extractor/prompts/gemini_page_ocr_prompt_v2.txt",
                 max_retries: int = 5, retry_delay: int = 2):
        """
        Initialize Gemini OCR service.
        
        Args:
            api_key: Google API key
            model: Gemini model name (gemini-1.5-pro or gemini-1.5-flash)
            prompt_file: Path to OCR prompt template
            max_retries: Maximum number of retry attempts
            retry_delay: Delay between retries in seconds
        """
        self.api_key = api_key
        self.model_name = model
        self.prompt_file = Path(prompt_file)
        self.max_retries = max_retries
        self.retry_delay = retry_delay
        
        try:
            import google.generativeai as genai
            from langchain_google_genai import ChatGoogleGenerativeAI
            
            genai.configure(api_key=api_key)
            self.direct_model = genai.GenerativeModel(model)
            
            self.llm = ChatGoogleGenerativeAI(
                model=model,
                google_api_key=api_key,
                temperature=0.1,
                max_output_tokens=8192
            )
        except ImportError as e:
            logger.error(f"Failed to import required modules: {e}")
            self.direct_model = None
            self.llm = None
        except Exception as e:
            logger.error(f"Failed to initialize Gemini API: {e}")
            self.direct_model = None
            self.llm = None
    
    def extract_text_from_image(self, image_path: str, page_number: int) -> Dict[str, Any]:
        """
        Extract text, equations, and diagrams from an image.
        
        Args:
            image_path: Path to the image file
            page_number: Page number for reference
            
        Returns:
            Dictionary with extracted content
        """
        if not self.direct_model:
            logger.error("Gemini model not initialized. Check API key and dependencies.")
            return {
                "page": page_number,
                "text": "",
                "questions": [],
                "equations": [],
                "diagrams": [],
                "tables": [],
                "error": "Gemini API not properly initialized. Please check API key and dependencies."
            }
        
        try:
            prompt = self._load_prompt_template()
            
            with open(image_path, "rb") as img_file:
                image_data = img_file.read()
            
            result = self._call_gemini_api(image_data, prompt)
            
            return self._parse_ocr_response(result, page_number)
            
        except FileNotFoundError:
            logger.error(f"Image file not found: {image_path}")
            return {
                "page": page_number,
                "text": "",
                "questions": [],
                "equations": [],
                "diagrams": [],
                "tables": [],
                "error": f"Image file not found: {image_path}"
            }
        except Exception as e:
            logger.error(f"OCR extraction failed for page {page_number}: {e}")
            return {
                "page": page_number,
                "text": "",
                "questions": [],
                "equations": [],
                "diagrams": [],
                "tables": [],
                "error": f"OCR extraction failed: {str(e)}"
            }
    
    def _load_prompt_template(self) -> str:
        """Load prompt template from file."""
        try:
            if self.prompt_file.exists():
                return self.prompt_file.read_text(encoding='utf-8')
            else:
                logger.warning(f"Prompt file not found: {self.prompt_file}")
                return "Extract all questions, equations, and diagrams from this image."
        except Exception as e:
            logger.error(f"Failed to load prompt template: {e}")
            return "Extract all questions, equations, and diagrams from this image."
    
    def _call_gemini_api(self, image_data: bytes, prompt: str) -> Dict[str, Any]:
        """
        Call Gemini API with image and prompt.
        
        Args:
            image_data: Image bytes
            prompt: Prompt text
            
        Returns:
            API response
        """
        if not self.direct_model:
            raise RuntimeError("Gemini model not initialized")
        
        try:
            import google.generativeai as genai
            
            image_part = {
                "mime_type": "image/png",
                "data": image_data
            }
            
            response = self.direct_model.generate_content([prompt, image_part])
            
            if not response or not response.text:
                raise ValueError("Empty response from Gemini API")
            
            return {"text": response.text}
            
        except Exception as e:
            logger.error(f"Gemini API call failed: {e}")
            raise
    
    def _parse_ocr_response(self, response: Dict[str, Any], page_number: int) -> Dict[str, Any]:
        """Parse OCR response into structured format."""
        try:
            text = response.get("text", "")
            
            return {
                "page": page_number,
                "text": text,
                "questions": [],
                "equations": [],
                "diagrams": [],
                "tables": []
            }
        except Exception as e:
            logger.error(f"Failed to parse OCR response: {e}")
            return {
                "page": page_number,
                "text": "",
                "questions": [],
                "equations": [],
                "diagrams": [],
                "tables": [],
                "error": f"Failed to parse response: {str(e)}"
            }

