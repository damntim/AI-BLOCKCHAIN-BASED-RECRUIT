import fitz  # pymupdf
import io

try:
    import pytesseract
    from PIL import Image
    _OCR_AVAILABLE = True
except ImportError:
    _OCR_AVAILABLE = False


def extract_text_from_pdf(data: bytes) -> str:
    """
    Extract text from a PDF using PyMuPDF with optional pytesseract OCR fallback
    for pages that yield no selectable text (scanned pages).
    """
    doc = fitz.open(stream=data, filetype="pdf")
    pages = []
    for page in doc:
        text = page.get_text().strip()
        if not text and _OCR_AVAILABLE:
            try:
                pix = page.get_pixmap(dpi=200)
                img = Image.open(io.BytesIO(pix.tobytes("png")))
                text = pytesseract.image_to_string(img).strip()
            except Exception:
                pass
        pages.append(text)
    return "\n".join(pages)
