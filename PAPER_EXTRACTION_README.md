# Paper Extraction System

This system allows you to extract structured data from academic question papers (PDFs and Word documents), making them searchable and processable in digital formats.

## Features

-   **Upload Papers**: Upload PDF or Word documents for extraction
-   **Automatic Question Extraction**: Automatically extracts questions from uploaded papers
-   **Structured Data Storage**: Stores questions with metadata (type, marks, topics, keywords)
-   **Search Functionality**: Search questions across all extracted papers
-   **Metadata Management**: Store additional metadata about papers (subject, year, level, exam board)
-   **Status Tracking**: Track extraction status (pending, processing, completed, failed)

## Database Structure

### Tables

1. **paper_extracts**: Main table storing paper extract information

    - Links to original papers (optional)
    - Stores extraction status and metadata
    - Tracks subject, year, level, exam board

2. **paper_extract_questions**: Stores individual questions extracted from papers

    - Question text (original and cleaned)
    - Question type (multiple choice, calculation, essay, etc.)
    - Marks, page number, topic, subtopic
    - Keywords for searchability
    - Options for multiple choice questions

3. **paper_extract_metadata**: Flexible metadata storage
    - Key-value pairs for additional information

## Installation

### 1. Run Migrations

```bash
php artisan migrate
```

### 2. Install PDF Parser Library

The system uses `smalot/pdfparser` for PDF processing. Install it via Composer:

```bash
composer require smalot/pdfparser
```

### 3. Configure Storage

Ensure your storage is properly configured in `config/filesystems.php` and that the `public` disk is linked:

```bash
php artisan storage:link
```

## Usage

### Backend API Endpoints

#### List Paper Extracts

```
GET /api/admin/paper-extracts
Query Parameters:
  - search: Search term
  - subject: Filter by subject
  - year: Filter by year
  - level: Filter by level
  - extraction_status: Filter by extraction status
  - per_page: Items per page
  - page: Page number
```

#### Create Paper Extract

```
POST /api/admin/paper-extracts
Content-Type: multipart/form-data

Fields:
  - title (required): Title of the paper
  - description: Description
  - paper_id: Link to existing paper (optional)
  - subject: Subject name
  - exam_board: Exam board name
  - year: Year
  - level: Level (e.g., GCSE, A-Level, 11 Plus)
  - source_file (required): PDF or Word document file
```

#### Get Paper Extract Details

```
GET /api/admin/paper-extracts/{id}
```

#### Update Paper Extract

```
PUT /api/admin/paper-extracts/{id}
```

#### Delete Paper Extract

```
DELETE /api/admin/paper-extracts/{id}
```

#### Search Questions

```
GET /api/admin/paper-extracts/{id}/questions/search
Query Parameters:
  - search (required): Search term
  - question_type: Filter by question type
  - topic: Filter by topic
  - subject: Filter by subject
```

#### Get Statistics

```
GET /api/admin/paper-extracts/statistics/overview
```

### Frontend

Access the Paper Extract page from the admin panel:

-   Navigate to: **Mock Exams > Paper Extract**

## Extraction Process

1. **Upload**: User uploads a PDF or Word document
2. **Processing**: System processes the file and extracts text
3. **Question Detection**: System identifies questions using pattern matching
4. **Data Extraction**: Extracts question details (number, text, type, marks, etc.)
5. **Keyword Extraction**: Extracts keywords for searchability
6. **Storage**: Stores structured data in database

## Question Detection Patterns

The system recognizes questions using patterns like:

-   `1. Question text...`
-   `Question 1: Question text...`
-   `(1) Question text...`

## Question Types Detected

-   **multiple_choice**: Questions with options
-   **calculation**: Questions requiring calculations
-   **essay**: Long-form questions
-   **true_false**: True/False questions
-   **short_answer**: Short answer questions

## Future Enhancements

-   OCR support for scanned PDFs
-   Machine learning for better question detection
-   Automatic answer extraction
-   Integration with question banks
-   Export to various formats (CSV, JSON, etc.)
-   Batch processing
-   Advanced search with filters
-   Question similarity detection

## Notes

-   Currently, Word document processing is not fully implemented. Convert Word documents to PDF for best results.
-   The extraction quality depends on the document structure and formatting.
-   For best results, ensure papers are well-formatted with clear question numbering.
