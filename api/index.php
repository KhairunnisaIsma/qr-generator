<?php
// api/index.php

require '../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Invalid JSON input');

    $text = $input['text'] ?? 'https://example.com';
    $size = (int)($input['size'] ?? 300);
    $margin = (int)($input['margin'] ?? 10);
    $fgHex = $input['fgColor'] ?? '#000000';
    $bgHex = $input['bgColor'] ?? '#ffffff';
    $logoUrl = $input['logoUrl'] ?? '';
    
    // Ambil format (default png)
    $format = strtolower($input['format'] ?? 'png');
    
    $isTransparent = filter_var($input['transparent'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // Setup Warna
    $fgColor = hexToColor($fgHex);
    // SVG & PNG support transparan, JPG tidak.
    if ($isTransparent && $format !== 'jpg') { 
        $bgColor = new Color(255, 255, 255, 127);
    } else {
        $bgColor = hexToColor($bgHex);
    }

    // Setup QR Code
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

    // Setup Logo
    $logo = null;
    if (!empty($logoUrl) && filter_var($logoUrl, FILTER_VALIDATE_URL)) {
        
        // FIX: Matikan punchoutBackground khusus untuk SVG
        // Karena SvgWriter akan error jika punchout = true
        $shouldPunchout = true;
        if ($format === 'svg') {
            $shouldPunchout = false;
        }

        $logo = new Logo(
            path: $logoUrl,
            resizeToWidth: (int)($size / 4),
            punchoutBackground: $shouldPunchout // Gunakan variabel dinamis ini
        );
    }

    $base64 = '';

    if ($format === 'svg') {
        // --- SVG WRITER ---
        $writer = new SvgWriter();
        $result = $writer->write($qrCode, $logo);
        $base64 = $result->getDataUri();
        
    } elseif ($format === 'jpg' || $format === 'jpeg') {
        // --- JPG MANUAL ---
        $writer = new PngWriter();
        $result = $writer->write($qrCode, $logo);
        $pngString = $result->getString();
        
        $image = imagecreatefromstring($pngString);
        ob_start();
        imagejpeg($image, null, 90);
        $jpgData = ob_get_clean();
        imagedestroy($image);
        
        $base64 = 'data:image/jpeg;base64,' . base64_encode($jpgData);

    } else {
        // --- PNG DEFAULT ---
        $writer = new PngWriter();
        $result = $writer->write($qrCode, $logo);
        $base64 = $result->getDataUri();
    }

    echo json_encode([
        'status' => 'success',
        'image'  => $base64,
        'format' => $format
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}