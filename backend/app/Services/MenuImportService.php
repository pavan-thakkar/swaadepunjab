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
        $swiftSource   = base_path('ocr_pdf.swift');

        // Compile Swift OCR tool on the fly if needed
        if (!file_exists($ocrExecutable) && file_exists($swiftSource)) {
            shell_exec("swiftc -O " . escapeshellarg($swiftSource) . " -o " . escapeshellarg($ocrExecutable));
        }

        if (!file_exists($ocrExecutable)) {
            throw new Exception("Native OCR tool could not be compiled. Please check Swift installation.");
        }

        // Write OCR output to a temp file to avoid shell_exec buffer limits
        $tmpOut = tempnam(sys_get_temp_dir(), 'ocr_') . '.json';
        $tmpErr = tempnam(sys_get_temp_dir(), 'ocr_err_') . '.txt';

        $cmd = escapeshellcmd($ocrExecutable)
             . ' ' . escapeshellarg($filePath)
             . ' > ' . escapeshellarg($tmpOut)
             . ' 2> ' . escapeshellarg($tmpErr);

        $exitCode = null;
        system($cmd, $exitCode);

        $stderr = file_exists($tmpErr) ? trim(file_get_contents($tmpErr)) : '';
        @unlink($tmpErr);

        if (!file_exists($tmpOut) || filesize($tmpOut) === 0) {
            throw new Exception("OCR tool returned empty result." . ($stderr ? " Error: $stderr" : ''));
        }

        // Read JSON from the temp file
        $output = file_get_contents($tmpOut);
        @unlink($tmpOut);

        if (empty(trim($output))) {
            throw new Exception("OCR output file was empty." . ($stderr ? " Error: $stderr" : ''));
        }

        // Strip any leading non-JSON content (e.g. debug lines printed before JSON)
        $jsonStart = strpos($output, '[');
        if ($jsonStart === false) {
            throw new Exception("OCR output does not contain valid JSON array. Output starts with: " . substr($output, 0, 200));
        }
        if ($jsonStart > 0) {
            $output = substr($output, $jsonStart);
        }

        $pages = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($pages)) {
            throw new Exception("Failed to parse OCR JSON: " . json_last_error_msg() . ". Output length: " . strlen($output) . " chars.");
        }

        $noiseRegex = '/^(we\'re|we accept|swad-e|100%|punja|phone|\+91|zomato|swiggy|available|page|\d+\/\d+|\=\s*\d+)/i';
        $importedCount = 0;

        foreach ($pages as $page) {
            $lines = $page['lines'] ?? [];
            if (empty($lines)) continue;

            $priceTokens = [];
            $textLines   = [];

            foreach ($lines as $line) {
                $text = trim($line['text'] ?? '');
                if (empty($text) || preg_match($noiseRegex, $text)) continue;

                // Check trailing price inside string (e.g. "Paneer Butter Masala 199")
                list($cleaned, $trailingPrice) = $this->extractTrailingPrice($text);
                if ($trailingPrice !== null && $this->isValidName($cleaned)) {
                    $textLines[] = [
                        'text'         => $cleaned,
                        'x'            => floatval($line['x']),
                        'y'            => floatval($line['y']),
                        'direct_price' => $trailingPrice
                    ];
                    continue;
                }

                // Check standalone price line (e.g. "199", "199/-", "₹199")
                $standalonePrice = $this->cleanPrice($text);
                if ($standalonePrice !== null) {
                    $priceTokens[] = [
                        'val' => $standalonePrice,
                        'x'   => floatval($line['x']),
                        'y'   => floatval($line['y'])
                    ];
                } elseif ($this->isValidName($text)) {
                    $textLines[] = [
                        'text'         => $text,
                        'x'            => floatval($line['x']),
                        'y'            => floatval($line['y']),
                        'direct_price' => null
                    ];
                }
            }

            // Pair text lines to price tokens
            $pairedDishes = [];
            foreach ($textLines as $item) {
                $price = $item['direct_price'];

                if ($price === null) {
                    // Match price on exact same row (y difference <= 12, price is to the right)
                    $bestToken = null;
                    $minDist   = INF;

                    foreach ($priceTokens as $pt) {
                        $yDiff = abs($pt['y'] - $item['y']);
                        $xDiff = $pt['x'] - $item['x'];
                        if ($yDiff <= 12 && $xDiff > 0 && $xDiff <= 250) {
                            if ($yDiff < $minDist) {
                                $minDist   = $yDiff;
                                $bestToken = $pt;
                            }
                        }
                    }
                    if ($bestToken !== null) {
                        $price = $bestToken['val'];
                    }
                }

                if ($price !== null) {
                    $cleanName = trim(preg_replace('/\s+/', ' ', $item['text']), " .-_~:/|+•=");
                    if ($this->isValidName($cleanName)) {
                        $pairedDishes[] = [
                            'name'  => $cleanName,
                            'price' => $price,
                            'x'     => $item['x'],
                            'y'     => $item['y']
                        ];
                    }
                }
            }

            // Insert into database
            foreach ($pairedDishes as $dish) {
                $category = $this->resolveCategory('', $dish['name']);

                MenuItem::create([
                    'name'         => $dish['name'],
                    'description'  => null,
                    'category'     => $category,
                    'price'        => $dish['price'],
                    'prep_time'    => 20,
                    'rating'       => 4.5,
                    'is_available' => true,
                    'is_featured'  => false,
                    'image'        => null,
                ]);

                $importedCount++;
            }
        }

        return $importedCount;
    }

    /**

     * Parse trailing price from text.
     */
    protected function extractTrailingPrice(string $text): array
    {
        if (preg_match('/(?:[\s\.\-\~_\:\|]+|(?:rs\.?|₹|inr)\s*)(\d{2,4}(?:\.\d{1,2})?)\s*(?:\/-|\/)?$/i', trim($text), $matches, PREG_OFFSET_CAPTURE)) {
            $val = floatval($matches[1][0]);
            // Fix OCR artifact where 1 is prepended: 1159 -> 159
            if ($val > 1000 && $val < 2000 && (str_starts_with((string)$val, '11') || str_starts_with((string)$val, '10'))) {
                $fix = floatval(substr((string)intval($val), 1));
                if ($fix >= 30 && $fix <= 500) $val = $fix;
            }
            if ($val >= 10 && $val <= 1500) {
                $cleanedText = trim(substr($text, 0, $matches[0][1]), " .-_~:/|+•=");
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
        $textClean = trim(preg_replace('/[\/\-\=\s]/', '', $text));
        if (preg_match('/^\d{2,4}(\.\d{1,2})?$/', $textClean, $m)) {
            $val = floatval($m[0]);
            if ($val > 1000 && $val < 2000 && (str_starts_with((string)$val, '11') || str_starts_with((string)$val, '10'))) {
                $fix = floatval(substr((string)intval($val), 1));
                if ($fix >= 30 && $fix <= 500) $val = $fix;
            }
            if ($val >= 10 && $val <= 1500) return $val;
        }
        if (preg_match('/^(?:rs\.?|₹|inr)?\s*(\d{2,4}(?:\.\d{1,2})?)$/i', trim($text), $matches)) {
            $val = floatval($matches[1]);
            if ($val >= 10 && $val <= 1500) return $val;
        }
        return null;
    }

    /**
     * Validate name against noise words.
     */
    protected function isValidName(string $text): bool
    {
        $clean = trim($text);
        if (strlen($clean) < 3) return false;
        if (preg_match('/^[\.\s\d\-\,\_\:\/\\\|\+\=\•]+$/', $clean)) return false;
        if (!preg_match('/[a-zA-Z]/', $clean)) return false;

        $lower = strtolower($clean);
        $noisePhrases = [
            'swad-e', 'punjab', 'we\'re also available', 'party orders', 
            'zomato & swiggy', '100% pure', 'page ', 'contact:', '+91'
        ];
        foreach ($noisePhrases as $np) {
            if (str_contains($lower, $np)) return false;
        }

        return true;
    }

    /**
     * Resolve menu category slug based on input category or keywords.
     */
    protected function resolveCategory(string $rawCategory, string $searchText): string
    {
        $originalRawCategory = trim($rawCategory);
        $rawCategory = strtolower($originalRawCategory);
        $searchText = strtolower($searchText);

        // If a category was provided in the CSV, dynamically create it if it doesn't exist
        if (!empty($originalRawCategory)) {
            $slug = \Illuminate\Support\Str::slug($originalRawCategory, '_');
            
            \App\Models\Category::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => ucwords($originalRawCategory),
                    'is_active' => true,
                    'sort_order' => \App\Models\Category::max('sort_order') + 1,
                    'emoji' => '🍽️',
                ]
            );
            
            return $slug;
        }

        // Chur Chur Naan
        if (str_contains($searchText, 'chur chur') || str_contains($searchText, 'chur_chur') || str_contains($rawCategory, 'chur chur')) {
            return 'chur_chur_naan';
        }

        // Rice Combos / Biryani / Khichadi / Pulao
        if (str_contains($searchText, 'pulao') || str_contains($searchText, 'khichadi') || str_contains($searchText, 'khichdi') || str_contains($searchText, 'biryani') || (str_contains($searchText, 'rice') && !str_contains($searchText, 'curry') && !str_contains($searchText, 'dal'))) {
            return 'rice_combo';
        }

        // Aloo Combos
        if (str_contains($searchText, 'aloo') && (str_contains($searchText, 'roti') || str_contains($searchText, 'puri') || str_contains($searchText, 'chaas') || str_contains($searchText, 'chass') || str_contains($searchText, 'matar'))) {
            return 'aloo_combo';
        }

        // Special Combos / Thali
        if (str_contains($searchText, 'thali') || str_contains($searchText, 'combo lunch') || str_contains($searchText, 'delux') || str_contains($searchText, 'special punjabi') || str_contains($searchText, 'amritsar fix')) {
            return 'special_combo';
        }

        // Sabji Combos (e.g. Kaju Curry with Naan, Cheese Butter Masala with Chur Chur, Amritsari Paneer with Garlic Naan)
        if ((str_contains($searchText, 'curry with') || str_contains($searchText, 'masala with') || str_contains($searchText, 'paneer with') || str_contains($searchText, 'combo')) && (str_contains($searchText, 'naan') || str_contains($searchText, 'roti') || str_contains($searchText, 'garlic'))) {
            return 'sabji_combo';
        }

        // Punjabi Sabji / Paneer / Dal / Rotis / Starters / Papad
        if (str_contains($searchText, 'paneer') || str_contains($searchText, 'dal') || str_contains($searchText, 'sabji') || str_contains($searchText, 'kofta') || str_contains($searchText, 'kolhapuri') || str_contains($searchText, 'roti') || str_contains($searchText, 'naan') || str_contains($searchText, 'paratha') || str_contains($searchText, 'papad') || str_contains($searchText, 'roll') || str_contains($searchText, 'kabab') || str_contains($searchText, 'fries') || str_contains($searchText, 'chole') || str_contains($searchText, 'chana')) {
            return 'punjabi_sabji';
        }

        return 'punjabi_sabji';
    }
}
