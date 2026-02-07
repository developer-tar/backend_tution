# PDF Question Paper Extractor

A full-stack application that extracts questions, diagrams, equations, and tables from PDF question papers using AI-powered OCR (Gemini Vision) and computer vision techniques.

## 🎯 Overview

This project provides both a **command-line interface (CLI)** and a **web application** for extracting structured data from PDF question papers. It uses Google's Gemini Vision API for OCR, LangChain for pipeline orchestration, and OpenCV for diagram detection and extraction.

## ✨ Features

- **Question Extraction**: Automatically identifies and extracts all questions from PDF papers
- **Diagram Detection**: Detects and extracts diagrams/images with bounding box coordinates
- **Equation Recognition**: Converts mathematical equations to LaTeX format
- **Table Extraction**: Identifies and extracts tabular data
- **Parallel Processing**: Multi-threaded processing for faster extraction
- **Web Interface**: Modern Next.js frontend with drag-and-drop file upload
- **REST API**: FastAPI backend with comprehensive error handling
- **Validation**: Built-in validation system to ensure extraction completeness

## 📋 Prerequisites

- **Python 3.8+**
- **Node.js 16+** and npm
- **Poppler** (for PDF processing)
  - Windows: Download from [poppler-windows](https://github.com/oschwartz10612/poppler-windows/releases)
  - Linux: `sudo apt-get install poppler-utils`
  - macOS: `brew install poppler`
- **Google API Key** for Gemini Vision API
  - Get your key from: https://makersuite.google.com/app/apikey

## 🚀 Installation

### 1. Install Python Dependencies

```bash
pip install -r requirements.txt
pip install -r backend_api/requirements.txt
```

### 2. Install Frontend Dependencies

```bash
cd frontend
npm install
cd ..
```

### 3. Configure Environment

Create a `.env` file in the project root:

```env
GOOGLE_API_KEY=your_api_key_here
GEMINI_MODEL=models/gemini-2.5-flash
DPI=200
MAX_WORKERS=4
```

**Note**: You can also set the API key in `backend_api/config.py` as a fallback.

### 4. Configure Poppler Path (Windows)

If using Windows, update the Poppler path in `backend_api/config.py`:

```python
POPPLER_PATH = r"C:\path\to\poppler\bin"
```

## 💻 Usage

### Command-Line Interface (CLI)

```bash
python main.py --pdf path/to/paper.pdf
```

### Web Application

Start the backend:
```bash
python -m backend_api.main
```

Start the frontend (in a new terminal):
```bash
cd frontend
npm run dev
```

Open your browser:
```
http://localhost:3000
```

## 📁 Project Structure

```
question-extractor/
├── backend_api/              # FastAPI backend
│   ├── main.py               # FastAPI application
│   ├── models.py             # Pydantic models
│   ├── config.py             # Configuration
│   └── requirements.txt      # Backend dependencies
│
├── frontend/                 # Next.js frontend
│   ├── app/                  # Next.js app directory
│   ├── components/           # React components
│   └── package.json
│
├── paper_extractor/          # Core extraction engine
│   ├── services/             # Core services
│   ├── pipeline/             # Main pipeline
│   └── utils/                # Utilities
│
├── main.py                   # CLI entry point
└── requirements.txt          # Core dependencies
```

## ⚙️ Configuration

### Backend Configuration (`backend_api/config.py`)

```python
# API Settings
DEFAULT_GOOGLE_API_KEY = "your_key_here"
DEFAULT_GEMINI_MODEL = "models/gemini-2.5-flash"
DEFAULT_DPI = 200
DEFAULT_MAX_WORKERS = 4

# Server Settings
API_HOST = "0.0.0.0"
API_PORT = 8000
DEBUG = True

# File Settings
MAX_FILE_SIZE = 10 * 1024 * 1024  # 10MB
CORS_ORIGINS = ["http://localhost:3000"]
```

## 🔧 Technologies Used

### Backend
- **Python 3.8+**
- **FastAPI**: Modern web framework
- **LangChain**: Pipeline orchestration
- **Gemini Vision API**: AI-powered OCR
- **OpenCV**: Image processing and diagram detection
- **pdf2image**: PDF to image conversion
- **Pydantic**: Data validation

### Frontend
- **Next.js 14**: React framework
- **React 18**: UI library
- **Tailwind CSS**: Styling
- **Axios**: HTTP client
- **MathJax**: Equation rendering
- **React Dropzone**: File upload

## 📊 Output Format

The extraction produces a structured JSON file with questions, diagrams, equations, and tables.

## 🐛 Troubleshooting

### Common Issues

1. **"GOOGLE_API_KEY not found"**
   - Ensure `.env` file exists with `GOOGLE_API_KEY` set
   - Or set it in `backend_api/config.py`

2. **"Poppler not found"**
   - Install Poppler and update path in `backend_api/config.py`

3. **"Cannot connect to server"**
   - Ensure backend is running on port 8000
   - Check CORS settings in `backend_api/config.py`

## 📝 License

This project is provided as-is for educational and research purposes.

---

**Built with ❤️ using Gemini Vision, LangChain, and OpenCV**

