"""
Convert PDF pages to images for OCR processing.
Uses pdf2image (poppler) for conversion.
"""

import logging
from pathlib import Path
from typing import List, Dict, Any

logger = logging.getLogger(__name__)


class PDFToImageConverter:
    """Convert PDF to page images."""

    def __init__(self, dpi: int = 250, output_dir: str = "paper_extractor/page_images"):
        self.dpi = dpi
        self.output_dir = Path(output_dir)
        self.output_dir.mkdir(parents=True, exist_ok=True)

    def convert(self, pdf_path: str, clean_output: bool = True) -> List[Dict[str, Any]]:
        """
        Convert PDF to images, one per page.

        Args:
            pdf_path: Path to the PDF file
            clean_output: If True, remove existing images in output_dir for this PDF (optional)

        Returns:
            List of dicts with "image_path" (str) and "page_number" (int)
        """
        try:
            from pdf2image import convert_from_path
        except ImportError as e:
            logger.error(f"pdf2image not installed: {e}. Run: pip install pdf2image. Poppler must be installed.")
            return []

        pdf_path = Path(pdf_path)
        if not pdf_path.exists():
            logger.error(f"PDF not found: {pdf_path}")
            return []

        try:
            images = convert_from_path(str(pdf_path), dpi=self.dpi)
        except Exception as e:
            logger.error(f"PDF conversion failed: {e}")
            return []

        page_data = []
        base_name = pdf_path.stem

        for i, img in enumerate(images):
            page_number = i + 1
            image_filename = f"{base_name}_page_{page_number:04d}.png"
            image_path = self.output_dir / image_filename
            try:
                img.save(str(image_path), "PNG")
                page_data.append({
                    "image_path": str(image_path),
                    "page_number": page_number,
                })
            except Exception as e:
                logger.warning(f"Failed to save page {page_number} image: {e}")

        return page_data
