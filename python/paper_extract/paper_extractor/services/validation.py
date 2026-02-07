"""
Extraction result validation (minimal for pipeline compatibility).
"""

import logging
from typing import Dict, Any, List

logger = logging.getLogger(__name__)


class ExtractionValidator:
    """Validates extraction result structure."""

    def validate(self, result: Dict[str, Any]) -> "ValidationResult":
        """Run basic validation on extraction result."""
        errors: List[str] = []
        warnings: List[str] = []

        questions = result.get("questions", [])
        if not questions:
            warnings.append("No questions extracted")

        return ValidationResult(
            valid=len(errors) == 0,
            total_errors=len(errors),
            total_warnings=len(warnings),
            errors=errors,
            warnings=warnings,
        )


class ValidationResult:
    """Result of validation with to_dict() for pipeline."""

    def __init__(
        self,
        valid: bool = True,
        total_errors: int = 0,
        total_warnings: int = 0,
        errors: List[str] = None,
        warnings: List[str] = None,
    ):
        self.valid = valid
        self.total_errors = total_errors or 0
        self.total_warnings = total_warnings or 0
        self.errors = errors or []
        self.warnings = warnings or []

    def to_dict(self) -> Dict[str, Any]:
        return {
            "valid": self.valid,
            "total_errors": self.total_errors,
            "total_warnings": self.total_warnings,
            "errors": self.errors,
            "warnings": self.warnings,
        }
