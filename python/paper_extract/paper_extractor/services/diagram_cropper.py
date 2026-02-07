"""
Diagram/image cropping service (stub for pipeline compatibility).
"""

import logging
from pathlib import Path

logger = logging.getLogger(__name__)


class DiagramCropper:
    """Extract/crop diagrams from images. Stub for pipeline init."""

    def __init__(self, output_dir: str = "paper_extractor/diagrams"):
        self.output_dir = Path(output_dir)
        self.output_dir.mkdir(parents=True, exist_ok=True)
