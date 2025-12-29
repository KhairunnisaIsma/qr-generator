<?php
// api/index.php

// 1. Path Autoload yang Aman untuk Vercel
require __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter; // Wajib untuk format SVG
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;

// 2. Setup Headers (CORS & JSON)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 3. Helper Function: Hex to RGB
function hexToColor($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return new Color($r, $g, $b);
}

try {
    // 4. Cek Extension GD (Penting untuk Vercel)
    if (!extension_loaded('gd')) {
        throw new Exception('GD extension is NOT loaded. Please create api/php.ini with extension=gd');
    }

    // 5. Ambil Input JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Invalid JSON input');

    // 6. Parsing Parameter
    $text = $input['text'] ?? 'https://example.com';
    $size = (int)($input['size'] ?? 300);
    $margin = (int)($input['margin'] ?? 10);
    $fgHex = $input['fgColor'] ?? '#000000';
    $bgHex = $input['bgColor'] ?? '#ffffff';
    $logoUrl = $input['logoUrl'] ?? '';
    
    // Format default ke png jika kosong
    $format = strtolower($input['format'] ?? 'png');
    
    // Cek Transparansi
    $isTransparent = filter_var($input['transparent'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // 7. Konfigurasi Warna
    $fgColor = hexToColor($fgHex);
    
    // Logic Transparan: SVG & PNG support, JPG tidak support.
    if ($isTransparent && $format !== 'jpg' && $format !== 'jpeg') { 
        // Alpha 127 = Full Transparent
        $bgColor = new Color(255, 255, 255, 127);
    } else {
        $bgColor = hexToColor($bgHex);
    }

    // 8. Buat Object QR Code
    $qrCode = new QrCode(
        data: $text,
        encoding: new Encoding('UTF-8'),
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size: $size,
        margin: $margin,
        roundBlockSizeMode: RoundBlockSizeMode::Margin,
        foregroundColor: $fgColor,
        backgroundColor: $bgColor
    );

    // 9. Buat Object Logo (Jika ada)
    $logo = null;
    if (!empty($logoUrl) && filter_var($logoUrl, FILTER_VALIDATE_URL)) {
        
        // FIX PENTING: SvgWriter tidak support punchoutBackground
        $shouldPunchout = true;
        if ($format === 'svg') {
            $shouldPunchout = false; 
        }

        $logo = new Logo(
            path: $logoUrl,
            resizeToWidth: (int)($size / 4), // Ukuran logo 1/4 dari QR
            punchoutBackground: $shouldPunchout
        );
    }

    // 10. Generate Output Berdasarkan Format
    $base64 = '';

    if ($format === 'svg') {
        // --- FORMAT SVG ---
        $writer = new SvgWriter();
        $result = $writer->write($qrCode, $logo);
        $base64 = $result->getDataUri();
        
    } elseif ($format === 'jpg' || $format === 'jpeg') {
        // --- FORMAT JPG (Manual Convert via GD) ---
        // Karena library Endroid v5+ menghapus JpgWriter, kita convert dari PNG
        $writer = new PngWriter();
        $result = $writer->write($qrCode, $logo);
        $pngString = $result->getString();
        
        // Baca string PNG sebagai gambar
        $image = imagecreatefromstring($pngString);
        if (!$image) throw new Exception('Failed to process image for JPG conversion');

        // Render ke JPG
        ob_start();
        imagejpeg($image, null, 90); // Kualitas 90
        $jpgData = ob_get_clean();
        imagedestroy($image);
        
        $base64 = 'data:image/jpeg;base64,' . base64_encode($jpgData);

    } else {
        // --- FORMAT PNG (Default) ---
        $writer = new PngWriter();
        $result = $writer->write($qrCode, $logo);
        $base64 = $result->getDataUri();
    }

    // 11. Kirim Response JSON
    echo json_encode([
        'status' => 'success',
        'image'  => $base64,
        'format' => $format
    ]);

} catch (Exception $e) {
    // Error Handling
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}