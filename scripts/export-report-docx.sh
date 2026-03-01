#!/usr/bin/env bash
set -euo pipefail

INPUT_MD="${1:-docs/human.md}"
OUTPUT_DOCX="${2:-docs/human.docx}"

if ! command -v pandoc >/dev/null 2>&1; then
    echo "Error: pandoc is not installed. Install with: brew install pandoc" >&2
    exit 1
fi

if [[ ! -f "$INPUT_MD" ]]; then
    echo "Error: input markdown file not found: $INPUT_MD" >&2
    exit 1
fi

INPUT_DIR="$(cd "$(dirname "$INPUT_MD")" && pwd)"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

pandoc_args=(
  "$INPUT_MD"
  --from=gfm+yaml_metadata_block
  --to=docx
  --output="$OUTPUT_DOCX"
  --resource-path="$INPUT_DIR:$PROJECT_ROOT/docs"
  --toc
  --toc-depth=3
  --lof
  --number-sections
  --shift-heading-level-by=-1
  --syntax-highlighting=none
  --wrap=preserve
)

pandoc "${pandoc_args[@]}"

if python3 -c "import docx" >/dev/null 2>&1; then
  python3 - "$OUTPUT_DOCX" <<'PY'
from __future__ import annotations
import sys

from docx import Document
from docx.enum.style import WD_STYLE_TYPE
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn
from docx.shared import Mm, Pt, RGBColor

BLACK = RGBColor(0, 0, 0)
TIMES = "Times New Roman"
CODE_SIZE = Pt(10)


def set_run_font(run, force_times: bool) -> None:
    run.font.color.rgb = BLACK
    if force_times:
        run.font.name = TIMES
        run._element.rPr.rFonts.set(qn("w:eastAsia"), TIMES)


def is_code_style(style_name: str) -> bool:
    lowered = style_name.lower()
    return "code" in lowered or "source" in lowered or "verbatim" in lowered


def is_toc_style(style_name: str) -> bool:
    return style_name.startswith("TOC ")


def ensure_paragraph_style(document: Document, style_name: str):
    try:
        return document.styles[style_name]
    except KeyError:
        return document.styles.add_style(style_name, WD_STYLE_TYPE.PARAGRAPH)


docx_path = sys.argv[1]
document = Document(docx_path)

normal_style = document.styles["Normal"]
normal_style.font.name = TIMES
normal_style.font.color.rgb = BLACK
normal_style._element.rPr.rFonts.set(qn("w:eastAsia"), TIMES)
normal_style.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY

for style in document.styles:
    style_name = style.name if style.name is not None else ""
    if is_toc_style(style_name):
        style.font.name = TIMES
        style.font.color.rgb = BLACK
        style._element.rPr.rFonts.set(qn("w:eastAsia"), TIMES)
    if style_name == "Hyperlink":
        style.font.color.rgb = BLACK
    if is_code_style(style_name):
        style.font.color.rgb = BLACK
        style.font.size = CODE_SIZE

for level in range(1, 10):
    toc_style = ensure_paragraph_style(document, f"TOC {level}")
    toc_style.font.name = TIMES
    toc_style.font.color.rgb = BLACK
    toc_style._element.rPr.rFonts.set(qn("w:eastAsia"), TIMES)

for paragraph in document.paragraphs:
    style_name = paragraph.style.name if paragraph.style is not None else ""
    is_heading = style_name.startswith("Heading")
    is_code = is_code_style(style_name)
    is_toc = is_toc_style(style_name)

    if not is_heading and not is_code and not is_toc:
        paragraph.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY

    for run in paragraph.runs:
        set_run_font(run, force_times=not is_code)
        if is_code:
            run.font.size = CODE_SIZE

for table in document.tables:
    for row in table.rows:
        for cell in row.cells:
            for paragraph in cell.paragraphs:
                style_name = paragraph.style.name if paragraph.style is not None else ""
                is_heading = style_name.startswith("Heading")
                is_code = is_code_style(style_name)
                is_toc = is_toc_style(style_name)

                if not is_heading and not is_code and not is_toc:
                    paragraph.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY

                for run in paragraph.runs:
                    set_run_font(run, force_times=not is_code)
                    if is_code:
                        run.font.size = CODE_SIZE

for section in document.sections:
    section.page_width = Mm(210)
    section.page_height = Mm(297)

document.save(docx_path)
PY
else
  echo "Warning: python-docx is not installed; DOCX style normalization was skipped." >&2
fi

echo "DOCX generated: $OUTPUT_DOCX"
