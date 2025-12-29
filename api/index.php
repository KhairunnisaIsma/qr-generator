<?php
// api/index.php

// --------------------------------------------------------------------------
// 1. DEBUGGING MODE (PENTING SAAT DEPLOY)
// --------------------------------------------------------------------------
// Baris ini akan memunculkan pesan error detail jika script crash.
// Setelah sukses production, baris ini bisa dikomentari/dihapus.
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// --------------------------------------------------------------------------
// 2. CORS HEADERS (Agar bisa diakses Frontend)
// --------------------------------------------------------------------------
// Handle Preflight Request (Browser cek izin dulu sebelum POST)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(200);
    exit();
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// --------------------------------------------------------------------------
// 3. LOAD LIBRARY (Path Aman untuk Vercel)
// --------------------------------------------------------------------------
// Menggunakan __DIR__ memastikan PHP mencari folder vendor relatif terhadap file ini
require __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter; // Wajib ada untuk SVG
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;

// --------------------------------------------------------------------------
// 4. HELPER FUNCTION
// --------------------------------------------------------------------------
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

// --------------------------------------------------------------------------
// 5. MAIN LOGIC
// --------------------------------------------------------------------------
try {
    // Cek Extension GD (Wajib untuk manipulasi gambar)
    if (!extension_loaded('gd')) {
        throw new Exception('Server Error: GD Extension is not enabled. Please check api/php.ini configuration.');
    }

    // Cek Extension XML (Wajib untuk SVG)
    if (!extension_loaded('xml')) {
        throw new Exception('Server Error: XML Extension is not enabled. Please check api/php.ini configuration.');
    }

    // Ambil Data Input
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);

    if (!$input) {
        // Jika JSON invalid atau kosong
        throw new Exception('Invalid JSON input received.');
    }

    // Default Values
    $text = $input['text'] ?? 'https://example.com';
    $size = (int)($input['size'] ?? 300);
    $margin = (int)($input['margin'] ?? 10);
    $fgHex = $input['fgColor'] ?? '#000000';
    $bgHex = $input['bgColor'] ?? '#ffffff';
    $logoUrl = $input['logoUrl'] ?? '';
    $format = strtolower($input['format'] ?? 'png');
    $isTransparent = filter_var($input['transparent'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // Setup Warna
    $fgColor = hexToColor($fgHex);
    
    // Logic: JPG tidak support transparan, paksa putih jika JPG
    if ($isTransparent && $format !== 'jpg' && $format !== 'jpeg') { 
        $bgColor = new Color(255, 255, 255, 127); // 127 = Alpha Max (Transparent)
    } else {
        $bgColor = hexToColor($bgHex);
    }

    // Buat QR Code Object
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

    // Buat Logo Object
    $logo = null;
    if (!empty($logoUrl) && filter_var($logoUrl, FILTER_VALIDATE_URL)) {
        
        // FIX: SvgWriter error jika punchoutBackground=true
        $shouldPunchout = true;
        if ($format === 'svg') {
            $shouldPunchout = false; 
        }

        try {
            $logo = new Logo(
                path: $logoUrl,
                resizeToWidth: (int)($size / 4),
                punchoutBackground: $shouldPunchout
            );
        } catch (Exception $e) {
            // Jika logo gagal diload (misal link mati), abaikan logo dan lanjut generate QR
            $logo = null; 
        }
    }

    // Generate Output
    $base64 = '';

    if ($format === 'svg') {
        // --- Output SVG ---
        $writer = new SvgWriter();
        $result = $writer->write($qrCode, $logo);
        $base64 = $result->getDataUri();
        
    } elseif ($format === 'jpg' || $format === 'jpeg') {
        // --- Output JPG (Manual Convert) ---
        $writer = new PngWriter();
        $result = $writer->write($qrCode, $logo);
        $pngString = $result->getString();
        
        $image = imagecreatefromstring($pngString);
        if (!$image) throw new Exception('Failed to convert QR to Image Resource.');

        ob_start();
        imagejpeg($image, null, 90); // Quality 90
        $jpgData = ob_get_clean();
        imagedestroy($image);
        
        $base64 = 'data:image/jpeg;base64,' . base64_encode($jpgData);

    } else {
        // --- Output PNG (Default) ---
        $writer = new PngWriter();
        $result = $writer->write($qrCode, $logo);
        $base64 = $result->getDataUri();
    }

    // Kirim Response Sukses
    echo json_encode([
        'status' => 'success',
        'image'  => $base64,
        'format' => $format
    ]);

} catch (Throwable $e) {
    // Catch All Error (termasuk Fatal Error PHP 7/8)
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(), // Info file error (untuk debug)
        'line' => $e->getLine()  // Info baris error (untuk debug)
    ]);
}