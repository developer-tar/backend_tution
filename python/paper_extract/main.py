# #!/usr/bin/env python3
# """
# PDF Question Paper Extractor - Main CLI Entry Point

# Extracts questions, diagrams, equations, and tables from PDF question papers
# using Gemini Vision OCR and LangChain.

# Usage:
#     python main.py --pdf input_pdfs/paper1.pdf
#     python main.py --pdf paper.pdf --dpi 600 --model gemini-1.5-pro
# """

# import argparse
# import logging
# import os
# import sys
# from pathlib import Path
# from datetime import datetime
# from typing import Optional

# from dotenv import load_dotenv
# from tqdm import tqdm

# load_dotenv()

# logging.basicConfig(level=logging.INFO)
# logger = logging.getLogger(__name__)


# def setup_logging(log_level: str = "INFO", log_file: Optional[str] = None) -> None:
#     """Configure logging for the application."""
#     level = getattr(logging, log_level.upper(), logging.INFO)
    
#     handlers = [logging.StreamHandler(sys.stdout)]
#     if log_file:
#         handlers.append(logging.FileHandler(log_file))
    
#     logging.basicConfig(
#         level=level,
#         format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
#         handlers=handlers
#     )


# def parse_arguments() -> argparse.Namespace:
#     """Parse command line arguments."""
#     parser = argparse.ArgumentParser(
#         description="Extract questions, diagrams, and equations from PDF question papers"
#     )
    
#     parser.add_argument("--pdf", required=True, help="Path to PDF file")
#     parser.add_argument("--dpi", type=int, default=600, help="DPI for PDF to image conversion")
#     parser.add_argument("--model", choices=["gemini-1.5-pro", "gemini-1.5-flash"], 
#                        default="gemini-1.5-pro", help="Gemini model to use")
#     parser.add_argument("--api-key", help="Google API key (default: from GOOGLE_API_KEY env variable)")
#     parser.add_argument("--output-dir", default="paper_extractor/output", 
#                        help="Output directory for results")
#     parser.add_argument("--page-images-dir", default="paper_extractor/page_images",
#                        help="Directory for page images")
#     parser.add_argument("--diagrams-dir", default="paper_extractor/diagrams",
#                        help="Directory for extracted diagrams")
#     parser.add_argument("--no-validate", action="store_true", help="Skip validation")
#     parser.add_argument("--log-level", choices=["DEBUG", "INFO", "WARNING", "ERROR"],
#                        default="INFO", help="Logging level")
#     parser.add_argument("--log-file", help="Log file path")
    
#     return parser.parse_args()


# def main():
#     """Main entry point."""
#     args = parse_arguments()
    
#     setup_logging(args.log_level, args.log_file)
    
#     pdf_path = Path(args.pdf)
#     if not pdf_path.exists():
#         logger.error(f"PDF file not found: {pdf_path}")
#         return 1
    
#     api_key = args.api_key or os.getenv("GOOGLE_API_KEY")
#     if not api_key:
#         logger.error("GOOGLE_API_KEY not provided. Set via --api-key or environment variable.")
#         return 1
    
#     try:
#         from paper_extractor.pipeline.extractor_pipeline import PaperExtractorPipeline
        
#         pipeline = PaperExtractorPipeline(
#             api_key=api_key,
#             model=args.model,
#             dpi=args.dpi,
#             output_dir=args.output_dir,
#             page_images_dir=args.page_images_dir,
#             diagrams_dir=args.diagrams_dir
#         )
        
#         logger.info(f"Starting extraction for: {pdf_path}")
#         result = pipeline.run(str(pdf_path), validate=not args.no_validate)
        
#         if result.get("validation", {}).get("valid", False):
#             logger.info("Extraction completed successfully")
#         else:
#             logger.warning("Extraction completed with warnings")
        
#         return 0
        
#     except ImportError as e:
#         logger.error(f"Failed to import required modules: {e}")
#         logger.error("Please ensure all dependencies are installed: pip install -r requirements.txt")
#         return 1
#     except Exception as e:
#         logger.error(f"Extraction failed: {e}")
#         import traceback
#         logger.error(traceback.format_exc())
#         return 1


# if __name__ == "__main__":
#     exit(main())

import argparse
import json
import os
import sys
import traceback

from paper_extractor.pipeline.extractor_pipeline import PaperExtractorPipeline

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--pdf", required=True)
    parser.add_argument("--output", required=True)

    args = parser.parse_args()

    api_key = os.environ.get("GOOGLE_API_KEY")
    if not api_key:
        raise ValueError("GOOGLE_API_KEY environment variable is required")

    pipeline = PaperExtractorPipeline(api_key=api_key)

    try:
        result = pipeline.run(args.pdf)

        with open(args.output, "w", encoding="utf-8") as f:
            json.dump(result, f, ensure_ascii=False, indent=2)

        print("SUCCESS")
        return 0

    except Exception as e:
        error_output = {
            "status": "error",
            "message": str(e),
            "traceback": traceback.format_exc()
        }

        with open(args.output, "w", encoding="utf-8") as f:
            json.dump(error_output, f, indent=2)

        print("FAILED", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
