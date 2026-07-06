<?php

namespace App\Services;

use App\Models\MenuItem;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class MenuImportService
{
    /**
     * Import menu items from a file path based on its extension/mime type.
     */
    public function import(string $filePath, string $originalName): int
    {
        // Temporarily increase memory limit for large file parsing
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            // Remove all existing menu items so only the imported ones appear
            MenuItem::query()->delete();

            if (in_array($extension, ['xlsx', 'xls', 'csv'])) {
                return $this->importExcel($filePath);
            } elseif ($extension === 'pdf') {
                return $this->importPdf($filePath);
            }

            throw new Exception("Unsupported file format. Please upload Excel (.xlsx, .xls), CSV (.csv), or PDF (.pdf).");
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }

    /**
     * Import from Excel or CSV.
     */
    protected function importExcel(string $filePath): int
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (empty($rows)) {
            return 0;
        }

        // Parse header row
        $headers = array_shift($rows);
        $headers = array_map(fn($h) => strtolower(trim($h ?? '')), $headers);

        // Find column indices
        $nameIdx = array_search('name', $headers);
        if ($nameIdx === false) $nameIdx = array_search('item', $headers);
        if ($nameIdx === false) $nameIdx = array_search('dish', $headers);

        $priceIdx = array_search('price', $headers);
        if ($priceIdx === false) $priceIdx = array_search('rate', $headers);
        if ($priceIdx === false) $priceIdx = array_search('cost', $headers);

        $descIdx = array_search('description', $headers);
        if ($descIdx === false) $descIdx = array_search('details', $headers);
        if ($descIdx === false) $descIdx = array_search('desc', $headers);

        $catIdx = array_search('category', $headers);
        $prepTimeIdx = array_search('prep_time', $headers);
        if ($prepTimeIdx === false) $prepTimeIdx = array_search('prep time', $headers);

        if ($nameIdx === false || $priceIdx === false) {
            throw new Exception("Required columns ('Name' and 'Price') not found in the spreadsheet headers.");
        }

        $importedCount = 0;

        foreach ($rows as $row) {
            $name = trim($row[$nameIdx] ?? '');
            $priceStr = trim($row[$priceIdx] ?? '');

            if (empty($name) || empty($priceStr)) {
                continue;
            }

            $price = floatval(preg_replace('/[^0-9.]/', '', $priceStr));
            $description = $descIdx !== false ? trim($row[$descIdx] ?? '') : null;
            $rawCategory = $catIdx !== false ? trim($row[$catIdx] ?? '') : '';
            $prepTime = $prepTimeIdx !== false ? intval($row[$prepTimeIdx] ?? 20) : 20;

            $category = $this->resolveCategory($rawCategory, $name . ' ' . ($description ?? ''));

            MenuItem::create([
                'name'         => $name,
                'description'  => $description,
                'category'     => $category,
                'price'        => $price,
                'prep_time'    => $prepTime > 0 ? $prepTime : 20,
                'rating'       => 4.5,
                'is_available' => true,
                'is_featured'  => false,
                'image'        => null,
            ]);

            $importedCount++;
        }

        return $importedCount;
    }

    /**
     * Import from PDF using native ocr_pdf tool with layout-aware matching.
     */
    protected function importPdf(string $filePath): int
    {
        $ocrExecutable = base_path('ocr_pdf');
        $swiftSource = base_path('ocr_pdf.swift');

        // Compile Swift OCR tool on the fly if needed
        if (!file_exists($ocrExecutable) && file_exists($swiftSource)) {
            shell_exec("swiftc -O " . escapeshellarg($swiftSource) . " -o " . escapeshellarg($ocrExecutable));
        }

        if (!file_exists($ocrExecutable)) {
            throw new Exception("Native OCR tool could not be compiled. Please check Swift installation.");
        }

        // Run the OCR tool
        $command = escapeshellcmd($ocrExecutable) . ' ' . escapeshellarg($filePath);
        $output = shell_exec($command);

        if (empty($output)) {
            throw new Exception("OCR tool returned empty result.");
        }

        $pages = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($pages)) {
            throw new Exception("Failed to parse OCR results: " . json_last_error_msg());
        }

        $importedCount = 0;
        $skipKeywords = [
            'menu', 'pure', 'veg', 'swad-e', 'punjab', 'party', 'order', 'orders', 
            'accept', 'zomato', 'swiggy', 'available', 'onion', 'chhas', 'papad', 
            'salad', 'chutney', 'chole', 'bhature', 'pcs', 'flaky', 'bread', 'gravies', 
            'finished', 'butter', 'finish', 'stretches', 'perfect', 'scoop', 'rich', 
            'ultra-thin', 'crispy', 'indian', 'hand-stretched', 'with', 'and', 'also',
            'phone', '+91', 'call', 'contact', 'accepts', "we're", 'thali', 'pure veg', 'swad-e punjab'
        ];

        // Process page by page
        foreach ($pages as $page) {
            $lines = $page['lines'] ?? [];
            $names = [];
            $prices = [];

            foreach ($lines as $line) {
                $text = trim($line['text'] ?? '');
                if (empty($text)) continue;

                // Check for trailing price in line (e.g. "Veg Pulao ... 109")
                list($cleanedText, $trailingPrice) = $this->extractTrailingPrice($text);
                if ($trailingPrice !== null && $this->isValidName($cleanedText, $skipKeywords)) {
                    $names[] = [
                        'text' => $cleanedText,
                        'x' => floatval($line['x']),
                        'y' => floatval($line['y']),
                        'w' => floatval($line['width']),
                        'h' => floatval($line['height']),
                        'direct_price' => $trailingPrice
                    ];
                    continue;
                }

                $pVal = $this->cleanPrice($text);
                if ($pVal !== null) {
                    $prices[] = [
                        'val' => $pVal,
                        'x' => floatval($line['x']),
                        'y' => floatval($line['y']),
                        'w' => floatval($line['width']),
                        'h' => floatval($line['height'])
                    ];
                } elseif ($this->isValidName($text, $skipKeywords)) {
                    $names[] = [
                        'text' => $text,
                        'x' => floatval($line['x']),
                        'y' => floatval($line['y']),
                        'w' => floatval($line['width']),
                        'h' => floatval($line['height']),
                        'direct_price' => null
                    ];
                }
            }

            // Pair names with prices
            foreach ($names as $name) {
                $resolvedPrice = null;

                if ($name['direct_price'] !== null) {
                    $resolvedPrice = $name['direct_price'];
                } else {
                    $bestPrice = null;
                    $minScore = INF;

                    foreach ($prices as $price) {
                        $yDiff = abs($name['y'] - $price['y']);
                        $xDiff = $price['x'] - $name['x'];

                        // Scenario A: Horizontal layout (price is on same row to the right)
                        if ($yDiff <= 15 && $xDiff > 0) {
                            $score = $xDiff + $yDiff * 3;
                            if ($score < $minScore) {
                                $minScore = $score;
                                $bestPrice = $price;
                            }
                        }
                        // Scenario B: Vertical layout (price is directly below, same column)
                        elseif ($yDiff > 0 && $price['y'] < $name['y'] && $yDiff <= 120 && abs($price['x'] - $name['x']) <= 50) {
                            $score = $yDiff * 2 + abs($price['x'] - $name['x']) * 1.5;
                            if ($score < $minScore) {
                                $minScore = $score;
                                $bestPrice = $price;
                            }
                        }
                    }

                    if ($bestPrice !== null && $minScore < 400) {
                        $resolvedPrice = $bestPrice['val'];
                    }
                }

                if ($resolvedPrice !== null) {
                    $cleanName = trim(preg_replace('/\s+/', ' ', $name['text']), " .-_~:/|+•= ");
                    
                    // Simple description extraction: find lines directly below this item in the same slot
                    $descriptionLines = [];
                    foreach ($names as $other) {
                        if ($other === $name) continue;
                        $xDiff = abs($other['x'] - $name['x']);
                        $yDiff = $name['y'] - $other['y'];
                        if ($xDiff <= 35 && $yDiff > 0 && $yDiff <= 45 && $other['direct_price'] === null) {
                            $descriptionLines[] = $other['text'];
                        }
                    }
                    $description = !empty($descriptionLines) ? implode(' ', $descriptionLines) : null;
                    $category = $this->resolveCategory('', $cleanName);

                    MenuItem::create([
                        'name'         => $cleanName,
                        'description'  => $description,
                        'category'     => $category,
                        'price'        => $resolvedPrice,
                        'prep_time'    => 20,
                        'rating'       => 4.5,
                        'is_available' => true,
                        'is_featured'  => false,
                        'image'        => null,
                    ]);

                    $importedCount++;
                }
            }
        }

        return $importedCount;
    }

    /**
     * Parse trailing price from text.
     */
    protected function extractTrailingPrice(string $text): array
    {
        if (preg_match('/[\s\.\-\~]+(\d+)\s*(?:\/-)?$/', trim($text), $matches, PREG_OFFSET_CAPTURE)) {
            $val = floatval($matches[1][0]);
            if ($val >= 10 && $val <= 2000) {
                $cleanedText = trim(substr($text, 0, $matches[0][1]), " .-_~:/|+");
                return [$cleanedText, $val];
            }
        }
        return [$text, null];
    }

    /**
     * Clean and parse standalone price.
     */
    protected function cleanPrice(string $text): ?float
    {
        $textClean = trim(str_replace(['/-', '-'], '', $text));
        if (preg_match('/^\d+(\.\d+)?$/', $textClean)) {
            $val = floatval($textClean);
            if ($val >= 10 && $val <= 2000) {
                return $val;
            }
        }
        if (preg_match('/^(?:rs\.?|₹|inr)?\s*(\d+(?:\.\d+)?)$/i', $textClean, $matches)) {
            $val = floatval($matches[1]);
            if ($val >= 10 && $val <= 2000) {
                return $val;
            }
        }
        return null;
    }

    /**
     * Validate name against noise words.
     */
    protected function isValidName(string $text, array $skipKeywords): bool
    {
        $textClean = strtolower(trim($text));
        if (empty($textClean)) {
            return false;
        }
        if (!preg_match('/[a-zA-Z]/', $textClean)) {
            return false;
        }
        if (strlen($textClean) < 3) { // Use strlen in PHP
            return false;
        }
        if (preg_match('/^[\d\s\-\.\:\,\/\\\|\+]+$/', $textClean)) {
            return false;
        }
        
        $words = preg_split('/[^a-z]+/', $textClean, -1, PREG_SPLIT_NO_EMPTY);
        $skipCount = 0;
        foreach ($words as $word) {
            if (in_array($word, $skipKeywords)) {
                $skipCount++;
            }
        }
        
        if ($skipCount > 0 && $skipCount === count($words)) {
            return false;
        }
        
        return true;
    }

    /**
     * Resolve menu category slug based on input category or keywords.
     */
    protected function resolveCategory(string $rawCategory, string $searchText): string
    {
        $rawCategory = strtolower(trim($rawCategory));
        $searchText = strtolower($searchText);

        // Explicit match
        if (str_contains($rawCategory, 'combo') || str_contains($rawCategory, 'thali')) {
            if (str_contains($rawCategory, 'sabji') || str_contains($rawCategory, 'paneer')) {
                return 'sabji_combo';
            }
            if (str_contains($rawCategory, 'aloo')) {
                return 'aloo_combo';
            }
            if (str_contains($rawCategory, 'rice') || str_contains($rawCategory, 'pulao')) {
                return 'rice_combo';
            }
            return 'special_combo';
        }
        if (str_contains($rawCategory, 'naan')) {
            return 'chur_chur_naan';
        }
        if (str_contains($rawCategory, 'sabji') || str_contains($rawCategory, 'paneer') || str_contains($rawCategory, 'dal')) {
            return 'punjabi_sabji';
        }
        if (str_contains($rawCategory, 'aloo')) {
            return 'aloo_combo';
        }
        if (str_contains($rawCategory, 'rice') || str_contains($rawCategory, 'pulao') || str_contains($rawCategory, 'khichadi')) {
            return 'rice_combo';
        }

        // Keyword lookup in item name/description
        if (str_contains($searchText, 'chur chur') || str_contains($searchText, 'chur_chur')) {
            return 'chur_chur_naan';
        }
        if (str_contains($searchText, 'pulao') || str_contains($searchText, 'khichadi') || str_contains($searchText, 'khichdi') || (str_contains($searchText, 'rice') && !str_contains($searchText, 'curry') && !str_contains($searchText, 'dal'))) {
            return 'rice_combo';
        }
        if (str_contains($searchText, 'aloo') && (str_contains($searchText, 'roti') || str_contains($searchText, 'puri') || str_contains($searchText, 'chaas') || str_contains($searchText, 'matar'))) {
            return 'aloo_combo';
        }
        if (str_contains($searchText, 'thali') || str_contains($searchText, 'combo lunch') || str_contains($searchText, 'delux') || str_contains($searchText, 'special punjabi thali')) {
            return 'special_combo';
        }
        if (str_contains($searchText, 'naan') && (str_contains($searchText, 'dal makhani') || str_contains($searchText, 'paneer'))) {
            return 'sabji_combo';
        }
        if (str_contains($searchText, 'paneer') || str_contains($searchText, 'dal') || str_contains($searchText, 'sabji') || str_contains($searchText, 'kofta') || str_contains($searchText, 'kolhapuri')) {
            return 'punjabi_sabji';
        }

        // Default category
        return 'punjabi_sabji';
    }
}
