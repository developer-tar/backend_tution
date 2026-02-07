"""
Pydantic models for API requests and responses.
"""

from typing import List, Optional
from pydantic import BaseModel


class EquationModel(BaseModel):
    """Equation model."""
    latex: str
    position: Optional[List[float]] = None


class DiagramModel(BaseModel):
    """Diagram model."""
    image_path: str
    bbox: List[float]
    description: Optional[str] = ""


class TableModel(BaseModel):
    """Table model."""
    content: str
    bbox: List[float]


class QuestionModel(BaseModel):
    """Question model."""
    id: str
    page: int
    question_no: str
    text: str
    position: Optional[List[float]] = None
    equations: List[EquationModel] = []
    diagrams: List[DiagramModel] = []
    tables: List[TableModel] = []


class ExtractionMetadata(BaseModel):
    """Extraction metadata."""
    dpi: int
    model: str
    timestamp: str
    total_questions: int
    total_diagrams: int


class ValidationSummary(BaseModel):
    """Validation summary."""
    valid: bool
    total_errors: int
    total_warnings: int
    errors: List[str] = []
    warnings: List[str] = []


class ExtractionResponse(BaseModel):
    """Extraction response model."""
    success: bool
    extraction_id: str
    source_pdf: str
    total_pages: int
    questions: List[QuestionModel]
    extraction_metadata: ExtractionMetadata
    validation: Optional[ValidationSummary] = None
    error: Optional[str] = None


class ErrorResponse(BaseModel):
    """Error response model."""
    success: bool
    error: str
    details: Optional[str] = None

