import Foundation
import PDFKit
import Vision

// Check arguments
guard CommandLine.arguments.count > 1 else {
    print("Usage: ocr_pdf.swift <path-to-pdf>")
    exit(1)
}

let pdfPath = CommandLine.arguments[1]
let url = URL(fileURLWithPath: pdfPath)

guard let document = PDFDocument(url: url) else {
    print("Error: Could not load PDF at \(pdfPath)")
    exit(1)
}

struct OcrLine: Codable {
    let text: String
    let x: Double
    let y: Double
    let width: Double
    let height: Double
}

struct OcrPage: Codable {
    let pageIndex: Int
    let lines: [OcrLine]
}

var ocrPages: [OcrPage] = []

let pageCount = document.pageCount
for i in 0..<pageCount {
    guard let page = document.page(at: i) else { continue }
    
    // Render PDF page to CGImage
    // We use a high resolution (e.g. 150 DPI or higher) to ensure good OCR results
    let pageBounds = page.bounds(for: .mediaBox)
    let dpi: CGFloat = 200.0
    let scale = dpi / 72.0
    let width = Int(pageBounds.width * scale)
    let height = Int(pageBounds.height * scale)
    
    let colorSpace = CGColorSpaceCreateDeviceRGB()
    guard let context = CGContext(
        data: nil,
        width: width,
        height: height,
        bitsPerComponent: 8,
        bytesPerRow: 0,
        space: colorSpace,
        bitmapInfo: CGImageAlphaInfo.noneSkipLast.rawValue
    ) else {
        continue
    }
    
    // Fill background with white
    context.setFillColor(CGColor(red: 1, green: 1, blue: 1, alpha: 1))
    context.fill(CGRect(x: 0, y: 0, width: width, height: height))
    
    // Scale and translate context
    context.scaleBy(x: scale, y: scale)
    context.translateBy(x: -pageBounds.origin.x, y: -pageBounds.origin.y)
    
    // Render PDF page
    page.draw(with: .mediaBox, to: context)
    
    guard let cgImage = context.makeImage() else { continue }
    
    // Set up Vision text recognition request
    var pageLines: [OcrLine] = []
    let requestHandler = VNImageRequestHandler(cgImage: cgImage, options: [:])
    
    let request = VNRecognizeTextRequest { (request, error) in
        guard let observations = request.results as? [VNRecognizedTextObservation] else { return }
        
        for observation in observations {
            guard let topCandidate = observation.topCandidates(1).first else { continue }
            
            // Vision coordinates are normalized (0 to 1), origin is bottom-left
            let boundingBox = observation.boundingBox
            
            // Convert to page coordinate units (72 points per inch) for consistency
            let x = Double(boundingBox.origin.x * pageBounds.width)
            let y = Double(boundingBox.origin.y * pageBounds.height)
            let w = Double(boundingBox.width * pageBounds.width)
            let h = Double(boundingBox.height * pageBounds.height)
            
            pageLines.append(OcrLine(
                text: topCandidate.string,
                x: x,
                y: y,
                width: w,
                height: h
            ))
        }
    }
    
    request.recognitionLevel = .accurate
    request.usesLanguageCorrection = true
    
    do {
        try requestHandler.perform([request])
    } catch {
        print("Error performing OCR: \(error)")
    }
    
    ocrPages.append(OcrPage(pageIndex: i, lines: pageLines))
}

// Output JSON
let encoder = JSONEncoder()
encoder.outputFormatting = .prettyPrinted
if let jsonData = try? encoder.encode(ocrPages),
   let jsonString = String(data: jsonData, encoding: .utf8) {
    print(jsonString)
} else {
    print("Error encoding JSON")
}
