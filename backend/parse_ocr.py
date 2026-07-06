import json
import re
import sys
import subprocess

pdf_path = 'storage/app/private/temp-imports/01KWCEWGG34ARNFACYW62XH3AQ.pdf'
result = subprocess.run(['./ocr_pdf', pdf_path], capture_output=True, text=True)

if result.returncode != 0:
    print(f"Error running ocr_pdf: {result.stderr}")
    sys.exit(1)

try:
    pages = json.loads(result.stdout)
except Exception as e:
    print(f"Error parsing JSON: {e}\nOutput was: {result.stdout[:500]}")
    sys.exit(1)

print(f"Successfully loaded OCR data. Page count: {len(pages)}")

for page_idx, page in enumerate(pages):
    print(f"\n--- PAGE {page_idx} ROWS ---")
    lines = page.get('lines', [])
    
    # Group lines by Y coordinate (within 8 points)
    rows = []
    for line in lines:
        matched = False
        for row in rows:
            # Check average Y of the row
            avg_y = sum(item['y'] for item in row) / len(row)
            if abs(line['y'] - avg_y) <= 8:
                row.append(line)
                matched = True
                break
        if not matched:
            rows.append([line])
            
    # Sort rows from top to bottom (Y descending since PDF Kit/Vision origin is bottom-left)
    rows.sort(key=lambda r: sum(item['y'] for item in r) / len(r), reverse=True)
    
    # Sort elements inside each row from left to right (X ascending)
    for row in rows:
        row.sort(key=lambda item: item['x'])
        row_text = " | ".join(f"[{item['text']} (X:{round(item['x'],1)}, Y:{round(item['y'],1)})]" for item in row)
        print(row_text)
