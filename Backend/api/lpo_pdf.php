<?php
/**
 * LPO / Quotation / Invoice PDF Generator
 * 
 * Pure-PHP PDF writer (A4, Helvetica fonts, DCTDecode images) with zero
 * external dependencies so it works seamlessly on all hosting environments.
 * Renders professional executive-grade business documents with company logo,
 * symmetrical metadata cards, crisp typography, clean line-item tables,
 * financial totals, and terms/notes.
 *
 * Usage: /Backend/api/lpo_pdf.php?id=DOC_ID[&inline=1]
 */
declare(strict_types=1);

$temp_dir = sys_get_temp_dir();
if (is_writable($temp_dir)) session_save_path($temp_dir);
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auto_migrate.php';
require_once __DIR__ . '/../../Frontend/includes/config.php';

// ── Auth: must be a logged-in staff member with LPO view permission ──
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['super_admin', 'farm_manager', 'stock_manager', 'sales_staff'], true)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You must be logged in']);
    exit;
}
$pdo = getDatabaseConnection();
if (!$pdo) { http_response_code(500); exit('Database unavailable'); }
$role = $_SESSION['role'];
if ($role !== 'super_admin') {
    $perms = function_exists('kindRolePermissions') ? kindRolePermissions($pdo) : [];
    if (!(($perms[$role]['lpo']['view'] ?? 0) || ($perms[$role]['lpo']['edit'] ?? 0))) {
        http_response_code(403);
        exit('You do not have permission to view LPO documents');
    }
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) exit('Invalid document id');

$stmt = $pdo->prepare('SELECT d.*, u.username AS created_by_name FROM lpo_documents d LEFT JOIN users u ON u.id=d.created_by WHERE d.id=?');
$stmt->execute([$id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$doc) { http_response_code(404); exit('Document not found'); }

$itemsStmt = $pdo->prepare('SELECT * FROM lpo_items WHERE doc_id=? ORDER BY id');
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$farmName    = getSetting('farm_name', 'Kind Commodities Ltd');
$farmTagline = getSetting('farm_tagline', 'Quality Agricultural Commodities & Farm Management');
$farmEmail   = getSetting('farm_email', 'info@kindcommoditiesltd.com');
$farmPhone   = getSetting('farm_phone', '+254 700 000 000');
$farmAddr    = getSetting('farm_address', 'Kenya');
$currency    = getSetting('currency', 'KES');

$typeLabelMap = [
    'quotation' => 'QUOTATION',
    'lpo'       => 'LOCAL PURCHASE ORDER',
    'invoice'   => 'TAX INVOICE'
];
$typeLabel = $typeLabelMap[$doc['doc_type']] ?? strtoupper((string)$doc['doc_type']);

/* ════════════════════════════════════════════════════════════════
   Pure-PHP PDF helper functions & font metrics
   ════════════════════════════════════════════════════════════════ */

/** Convert a UTF-8 string to PDF-literal-safe Windows-1252 bytes. */
function pdfText(string $s): string {
    $s = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    $s = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    return $s;
}

/** Approximate Helvetica glyph widths (1/1000 em) for alignment/wrapping. */
function pdfWidth(string $s): float {
    static $w = [
        32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,
        48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,64=>1015,
        65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,
        91=>278,92=>278,93=>278,94=>469,95=>556,96=>333,97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,111=>556,112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584,
    ];
    $len = 0.0;
    $bytes = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
        $c = ord($bytes[$i]);
        $len += $w[$c] ?? 556;
    }
    return $len / 1000;
}

/** Wrap text into lines fitting within maxWidth points. Supports explicit newlines. */
function pdfWrap(string $s, float $maxWidth, float $size = 9): array {
    $out = [];
    $paragraphs = explode("\n", str_replace(["\r\n", "\r"], "\n", $s));
    foreach ($paragraphs as $p) {
        $words = preg_split('/\s+/', trim($p)) ?: [];
        if (empty($words) || (count($words) === 1 && $words[0] === '')) {
            $out[] = '';
            continue;
        }
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if (pdfWidth($candidate) * $size <= $maxWidth || $line === '') {
                $line = $candidate;
            } else {
                $out[] = $line;
                $line = $word;
            }
        }
        if ($line !== '') $out[] = $line;
    }
    return $out ?: [''];
}

function pdfDate(string $s): string {
    $t = strtotime($s);
    return $t ? date('d M Y', $t) : $s;
}

/**
 * Load farm logo (PNG or JPEG) and composite onto a clean white canvas
 * cached as JPEG for direct DCTDecode inclusion in PDF stream.
 */
function pdfLogoJpeg(): ?string {
    $logoSetting = function_exists('getSetting') ? getSetting('header_logo', '/Frontend/images/header logo.jpeg') : '/Frontend/images/header logo.jpeg';
    $possibleFiles = [
        dirname(__DIR__, 2) . $logoSetting,
        dirname(__DIR__, 2) . '/Frontend/images/Kind Commodities Ltd Logo_Final_FOOTER.png',
        dirname(__DIR__, 2) . '/Frontend/images/header logo.jpeg',
        dirname(__DIR__, 2) . '/Frontend/images/footerlogo.jpeg'
    ];
    
    $srcFile = null;
    foreach ($possibleFiles as $f) {
        if (is_file($f)) { $srcFile = $f; break; }
    }
    if (!$srcFile) return null;
    
    $cache = sys_get_temp_dir() . '/kind_logo_pdf_v4.jpg';
    if (is_file($cache) && filemtime($cache) >= filemtime($srcFile)) {
        return $cache;
    }
    
    if (!function_exists('imagecreatetruecolor')) {
        return (strtolower(pathinfo($srcFile, PATHINFO_EXTENSION)) === 'jpg' || strtolower(pathinfo($srcFile, PATHINFO_EXTENSION)) === 'jpeg') ? $srcFile : null;
    }
    
    try {
        $ext = strtolower(pathinfo($srcFile, PATHINFO_EXTENSION));
        if ($ext === 'png' && function_exists('imagecreatefrompng')) {
            $src = @imagecreatefrompng($srcFile);
        } elseif (($ext === 'jpg' || $ext === 'jpeg') && function_exists('imagecreatefromjpeg')) {
            $src = @imagecreatefromjpeg($srcFile);
        } else {
            return null;
        }
        if (!$src) return null;
        
        $w = imagesx($src);
        $h = imagesy($src);
        
        $canvas = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagealphablending($canvas, true);
        imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
        imagejpeg($canvas, $cache, 95);
        imagedestroy($src);
        imagedestroy($canvas);
        return $cache;
    } catch (Throwable $e) {
        return null;
    }
}

/* ════════════════════════════════════════════════════════════════
   Minimal Pure-PHP PDF Builder (A4, Helvetica, DeviceRGB)
   ════════════════════════════════════════════════════════════════ */
$pdf = new class($doc, $items) {
    public array $doc;
    public array $items;
    public float $W = 595.28, $H = 841.89, $margin = 38;
    public float $y;
    public array $pages = [];
    public int $pageNo = 0;
    public array $fonts = [];
    public ?array $logo = null;
    public array $images = [];

    public function __construct(array $doc, array $items) {
        $this->doc = $doc;
        $this->items = $items;
        $this->newPage();
    }

    public function op(string $s): void {
        $this->pages[$this->pageNo - 1]['ops'] .= $s . "\n";
    }

    public function newPage(): void {
        $this->pages[] = ['ops' => '', 'footer' => []];
        $this->pageNo = count($this->pages);
        $this->y = $this->margin;
    }

    public function ensure(float $h): void {
        if ($this->y + $h > $this->H - 52) {
            $this->newPage();
        }
    }

    public function setFill(array $c): void {
        $this->op(sprintf('%.3f %.3f %.3f rg', $c[0], $c[1], $c[2]));
    }

    public function setStroke(array $c): void {
        $this->op(sprintf('%.3f %.3f %.3f RG', $c[0], $c[1], $c[2]));
    }

    public function rect(float $x, float $y, float $w, float $h, array $fillColor, ?array $strokeColor = null, float $lw = 0.5): void {
        $this->setFill($fillColor);
        if ($strokeColor !== null) {
            $this->setStroke($strokeColor);
            $this->op(sprintf('%.2f w %.2f %.2f %.2f %.2f re B', $lw, $x, $this->H - $y - $h, $w, $h));
        } else {
            $this->op(sprintf('%.2f %.2f %.2f %.2f re f', $x, $this->H - $y - $h, $w, $h));
        }
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $lw, array $c): void {
        $this->setStroke($c);
        $this->op(sprintf('%.2f w %.2f %.2f m %.2f %.2f l S', $lw, $x1, $this->H - $y1, $x2, $this->H - $y2));
    }

    /** Draw text at specified baseline position. */
    public function text(float $x, float $baselineY, string $s, float $size = 9, string $font = 'F1', array $color = [0.12, 0.16, 0.24], string $align = 'left', float $maxWidth = 0): void {
        $fontRes = $this->fontRes($font);
        $this->setFill($color);
        $this->op('BT /' . $fontRes . ' ' . sprintf('%.1f', $size) . ' Tf');
        $txt = $s;
        if ($maxWidth > 0) {
            $wrap = pdfWrap($s, $maxWidth, $size);
            $txt = $wrap[0] ?? $s;
        }
        $tw = pdfWidth($txt) * $size;
        if ($align === 'right') $x -= $tw;
        if ($align === 'center') $x -= ($tw / 2);
        
        $this->op(sprintf('%.2f %.2f Td (%s) Tj ET', $x, $this->H - $baselineY, pdfText($txt)));
    }

    /** Draw vertically-centered text inside a bounding box. */
    public function textInBox(float $x, float $boxTop, float $boxHeight, string $s, float $size = 9, string $font = 'F1', array $color = [0.12, 0.16, 0.24], string $align = 'left', float $maxWidth = 0): void {
        $baseline = $boxTop + ($boxHeight / 2) + ($size * 0.33);
        $this->text($x, $baseline, $s, $size, $font, $color, $align, $maxWidth);
    }

    public function fontRes(string $name): string {
        $this->fonts[$name] = true;
        static $map = ['F1' => 'F1', 'F2' => 'F2', 'F3' => 'F3', 'F4' => 'F4'];
        return $map[$name] ?? 'F1';
    }
};

$G = $pdf;
$logoPath = pdfLogoJpeg();
if ($logoPath && file_exists($logoPath)) {
    $info = @getimagesize($logoPath);
    if ($info && $info[0] > 0 && $info[1] > 0) {
        $G->logo = ['file' => $logoPath, 'w' => $info[0], 'h' => $info[1]];
    }
}

/* ════════════════════════════════════════════════════════════════
   Executive Document Design & Palette
   ════════════════════════════════════════════════════════════════ */
$navyPrimary = [0.118, 0.227, 0.373]; // #1E3A5F Deep Slate Blue
$amberGold   = [0.851, 0.584, 0.059]; // #D97706 Golden Accent
$textDark    = [0.059, 0.090, 0.165]; // #0F172A Near Black
$textSlate   = [0.278, 0.333, 0.412]; // #475569 Medium Slate
$textLight   = [0.392, 0.455, 0.545]; // #64748B Subdued Slate
$bgCard      = [0.973, 0.980, 0.988]; // #F8FAFC Subtle Gray Card
$bgAltRow    = [0.965, 0.976, 0.988]; // #F6F9FC Subtle Zebra Row
$borderLight = [0.886, 0.910, 0.941]; // #E2E8F0 Clean Border
$white       = [1.0, 1.0, 1.0];

$contentW = $G->W - ($G->margin * 2); // 519.28 pt

/* ── 1. HEADER BANNER ── */
$bannerH = 92;
$G->rect(0, 0, $G->W, $bannerH, $navyPrimary);
$G->line(0, $bannerH, $G->W, $bannerH, 3.0, $amberGold);

$txtStartX = $G->margin;
if ($G->logo) {
    $logoMaxH = 46;
    $logoAspect = $G->logo['w'] / $G->logo['h'];
    $logoDisplayW = min(140, $logoMaxH * $logoAspect);
    $logoDisplayH = $logoDisplayW / $logoAspect;
    
    $logoCardX = $G->margin;
    $logoCardY = 16;
    $logoCardW = $logoDisplayW + 16;
    $logoCardH = 58;
    $G->rect($logoCardX, $logoCardY, $logoCardW, $logoCardH, $white);
    
    $G->images[] = $G->logo;
    $imgDrawX = $logoCardX + 8;
    $imgDrawY = $logoCardY + ($logoCardH - $logoDisplayH) / 2;
    $G->op(sprintf('q %.2f 0 0 %.2f %.2f %.2f cm /Im1 Do Q', $logoDisplayW, $logoDisplayH, $imgDrawX, $G->H - $imgDrawY - $logoDisplayH));
    
    $txtStartX = $logoCardX + $logoCardW + 16;
}

$G->text($txtStartX, 34, $farmName, 17, 'F2', $white);
$G->text($txtStartX, 50, $farmTagline, 8.5, 'F1', [0.85, 0.92, 0.98]);
$G->text($txtStartX, 66, "Tel: {$farmPhone}   |   Email: {$farmEmail}", 8.5, 'F1', [0.85, 0.92, 0.98]);
$G->text($txtStartX, 79, "Location: {$farmAddr}   |   Web: kindcommoditiesltd.com", 8.5, 'F1', [0.85, 0.92, 0.98]);

/* ── 2. SYMMETRICAL 2-COLUMN HEADER (BILL TO & DOCUMENT DETAILS) ── */
$topSecY = $bannerH + 18;
$colW = ($contentW - 16) / 2; // ~251.6 pt each

// Prepare Bill-to Lines
$billLines = [
    ['label' => '', 'val' => (string)$doc['customer_name'], 'font' => 'F2', 'size' => 11, 'color' => $textDark]
];
if (!empty($doc['customer_phone'])) {
    $billLines[] = ['label' => 'Phone: ', 'val' => (string)$doc['customer_phone'], 'font' => 'F1', 'size' => 9, 'color' => $textSlate];
}
if (!empty($doc['customer_email'])) {
    $billLines[] = ['label' => 'Email: ', 'val' => (string)$doc['customer_email'], 'font' => 'F1', 'size' => 9, 'color' => $textSlate];
}
if (!empty($doc['customer_address'])) {
    $billLines[] = ['label' => 'Address: ', 'val' => (string)$doc['customer_address'], 'font' => 'F1', 'size' => 9, 'color' => $textSlate];
}

$cardHeight = max(94, 28 + (count($billLines) * 14) + 8);

// LEFT COLUMN: BILL TO CARD
$G->rect($G->margin, $topSecY, $colW, $cardHeight, $bgCard, $borderLight, 0.8);
$G->textInBox($G->margin + 12, $topSecY + 4, 18, 'BILL TO / RECIPIENT', 8.0, 'F2', $navyPrimary);
$G->line($G->margin + 12, $topSecY + 24, $G->margin + $colW - 12, $topSecY + 24, 0.6, $borderLight);

$curY = $topSecY + 39;
foreach ($billLines as $b) {
    $lineTxt = $b['label'] . $b['val'];
    $G->text($G->margin + 12, $curY, $lineTxt, $b['size'], $b['font'], $b['color']);
    $curY += 14;
}

// RIGHT COLUMN: DOCUMENT INFO CARD
$rightColX = $G->margin + $colW + 16;
$G->rect($rightColX, $topSecY, $colW, $cardHeight, $bgCard, $borderLight, 0.8);

$G->text($rightColX + $colW - 12, $topSecY + 20, $typeLabel, 15, 'F2', $navyPrimary, 'right');
$G->text($rightColX + $colW - 12, $topSecY + 36, '#' . (string)$doc['doc_number'], 11, 'F2', $textDark, 'right');
$G->line($rightColX + 12, $topSecY + 44, $rightColX + $colW - 12, $topSecY + 44, 0.6, $borderLight);

$metaY = $topSecY + 58;
$G->text($rightColX + 12, $metaY, 'Issue Date:', 8.5, 'F1', $textLight);
$G->text($rightColX + $colW - 12, $metaY, pdfDate((string)$doc['issue_date']), 8.5, 'F2', $textDark, 'right');

$metaY += 14;
if (!empty($doc['due_date'])) {
    $dueLabel = ($doc['doc_type'] === 'quotation') ? 'Valid Until:' : 'Due Date:';
    $G->text($rightColX + 12, $metaY, $dueLabel, 8.5, 'F1', $textLight);
    $G->text($rightColX + $colW - 12, $metaY, pdfDate((string)$doc['due_date']), 8.5, 'F2', $textDark, 'right');
    $metaY += 14;
}

// Status Pill
$statusStr = strtoupper((string)$doc['status']);
$pillW = pdfWidth($statusStr) * 8.0 + 16;
$pillH = 15;
$pillX = $rightColX + $colW - 12 - $pillW;
$pillY = $topSecY + $cardHeight - $pillH - 8;

$isPositiveStatus = in_array($doc['status'], ['paid', 'accepted', 'completed'], true);
$isInfoStatus     = in_array($doc['status'], ['sent', 'invoiced'], true);

if ($isPositiveStatus) {
    $pillBg = [0.86, 0.99, 0.90]; $pillText = [0.08, 0.50, 0.24]; $pillBorder = [0.73, 0.95, 0.79];
} elseif ($isInfoStatus) {
    $pillBg = [0.86, 0.92, 1.0]; $pillText = [0.12, 0.25, 0.69]; $pillBorder = [0.75, 0.85, 0.99];
} else {
    $pillBg = [0.94, 0.96, 0.98]; $pillText = [0.28, 0.33, 0.41]; $pillBorder = [0.85, 0.89, 0.93];
}

$G->rect($pillX, $pillY, $pillW, $pillH, $pillBg, $pillBorder, 0.5);
$G->textInBox($pillX + ($pillW / 2), $pillY, $pillH, $statusStr, 8.0, 'F2', $pillText, 'center');
$G->text($rightColX + 12, $pillY + 11, 'Status:', 8.5, 'F1', $textLight);

/* ── 3. LINE ITEMS TABLE ── */
$tableTopY = $topSecY + $cardHeight + 16;
$G->y = $tableTopY;

$colX = [
    'no'    => $G->margin,
    'desc'  => $G->margin + 26,
    'qty'   => $G->margin + 270,
    'unit'  => $G->margin + 320,
    'price' => $G->margin + 372,
    'amt'   => $G->margin + 446,
    'right' => $G->margin + $contentW
];

// Table Header Bar
$tblHeadH = 24;
$G->rect($G->margin, $G->y, $contentW, $tblHeadH, $navyPrimary);

$G->textInBox($colX['no'] + 4, $G->y, $tblHeadH, '#', 8.5, 'F2', $white, 'left');
$G->textInBox($colX['desc'], $G->y, $tblHeadH, 'ITEM DESCRIPTION', 8.5, 'F2', $white, 'left');
$G->textInBox($colX['qty'] + 42, $G->y, $tblHeadH, 'QTY', 8.5, 'F2', $white, 'right');
$G->textInBox($colX['unit'] + 25, $G->y, $tblHeadH, 'UNIT', 8.5, 'F2', $white, 'center');
$G->textInBox($colX['price'] + 68, $G->y, $tblHeadH, 'UNIT PRICE', 8.5, 'F2', $white, 'right');
$G->textInBox($colX['amt'] + 68, $G->y, $tblHeadH, 'AMOUNT (' . $currency . ')', 8.5, 'F2', $white, 'right');

$G->y += $tblHeadH;

// Table Body Rows
$lh = 12.0;
$rowIdx = 0;
foreach ($items as $it) {
    $rowIdx++;
    $descStr = (string)$it['description'];
    $descWrap = pdfWrap($descStr, 238, 9.0);
    $numLines = count($descWrap);
    
    $rowH = max(26, ($numLines * $lh) + 12);
    $G->ensure($rowH + 8);
    
    $rowBg = ($rowIdx % 2 === 1) ? $white : $bgAltRow;
    $G->rect($G->margin, $G->y, $contentW, $rowH, $rowBg);
    
    $firstLineBaseline = $G->y + 15;
    $G->text($colX['no'] + 4, $firstLineBaseline, (string)$rowIdx, 8.5, 'F1', $textLight);
    
    foreach ($descWrap as $lIdx => $lTxt) {
        $lineBase = $G->y + 15 + ($lIdx * $lh);
        $G->text($colX['desc'], $lineBase, $lTxt, 9.0, 'F1', $textDark);
    }
    
    $G->text($colX['qty'] + 42, $firstLineBaseline, number_format((float)$it['quantity'], 2), 9.0, 'F1', $textDark, 'right');
    $G->text($colX['unit'] + 25, $firstLineBaseline, (string)($it['unit'] ?: 'pcs'), 8.5, 'F1', $textSlate, 'center');
    $G->text($colX['price'] + 68, $firstLineBaseline, number_format((float)$it['unit_price'], 2), 9.0, 'F1', $textDark, 'right');
    $G->text($colX['amt'] + 68, $firstLineBaseline, number_format((float)$it['line_total'], 2), 9.0, 'F2', $textDark, 'right');
    
    $G->line($G->margin, $G->y + $rowH, $colX['right'], $G->y + $rowH, 0.5, $borderLight);
    $G->y += $rowH;
}

/* ── 4. TOTALS & SUMMARY SECTION ── */
$G->ensure(120);
$G->y += 10;

$totBoxW = 230;
$totBoxX = $colX['right'] - $totBoxW;

$summaryRows = [
    ['Subtotal', number_format((float)$doc['subtotal'], 2)]
];
if ((float)$doc['tax_rate'] > 0 || (float)$doc['tax_amount'] > 0) {
    $taxPct = rtrim(rtrim(number_format((float)$doc['tax_rate'], 2), '0'), '.');
    $summaryRows[] = ["VAT / Tax ({$taxPct}%)", number_format((float)$doc['tax_amount'], 2)];
}
if ((float)$doc['discount'] > 0) {
    $summaryRows[] = ['Discount', '- ' . number_format((float)$doc['discount'], 2)];
}

foreach ($summaryRows as [$sLabel, $sVal]) {
    $G->text($totBoxX + 8, $G->y + 11, $sLabel, 9.0, 'F1', $textSlate);
    $G->text($totBoxX + $totBoxW - 8, $G->y + 11, $sVal, 9.0, 'F2', $textDark, 'right');
    $G->y += 18;
}

$G->y += 2;
$grandBarH = 28;
$G->rect($totBoxX, $G->y, $totBoxW, $grandBarH, $navyPrimary);
$G->textInBox($totBoxX + 12, $G->y, $grandBarH, 'TOTAL DUE (' . $currency . ')', 10.0, 'F2', $white, 'left');
$G->textInBox($totBoxX + $totBoxW - 12, $G->y, $grandBarH, number_format((float)$doc['total_amount'], 2), 12.5, 'F2', $white, 'right');
$G->y += $grandBarH + 16;

/* ── 5. NOTES & PAYMENT TERMS ── */
if (!empty($doc['notes'])) {
    $notesWrap = pdfWrap((string)$doc['notes'], $contentW - 24, 8.5);
    $notesH = (count($notesWrap) * 12.5) + 26;
    $G->ensure($notesH + 10);
    
    $G->rect($G->margin, $G->y, $contentW, $notesH, [0.99, 0.98, 0.95], [0.94, 0.88, 0.72], 0.7);
    $G->textInBox($G->margin + 12, $G->y + 4, 14, 'TERMS & PAYMENT NOTES', 8.0, 'F2', $amberGold);
    
    $nY = $G->y + 24;
    foreach ($notesWrap as $nLine) {
        $G->text($G->margin + 12, $nY, $nLine, 8.5, 'F1', [0.33, 0.28, 0.20]);
        $nY += 12.5;
    }
    $G->y += $notesH + 14;
}

/* ── 6. AUTHORIZATION / SIGNATURE ── */
$G->ensure(40);
$G->line($G->margin, $G->y, $G->margin + 180, $G->y, 0.6, $borderLight);
$G->text($G->margin, $G->y + 12, 'Authorized Signature / Stamp', 8.0, 'F1', $textLight);
if (!empty($doc['created_by_name'])) {
    $G->text($G->margin, $G->y + 24, 'Prepared by: ' . $doc['created_by_name'], 8.0, 'F2', $textSlate);
}

/* ── 7. RUNNING FOOTER ON EVERY PAGE ── */
$totalPages = count($G->pages);
foreach ($G->pages as $idx => $p) {
    $fTop = $G->H - 38;
    $G->pages[$idx]['footer'] = [
        'top'   => $fTop,
        'line1' => "{$farmName}  •  {$farmAddr}  •  Tel: {$farmPhone}  •  {$farmEmail}",
        'line2' => "Thank you for choosing Kind Commodities. Quality agricultural products you can trust.",
        'page'  => 'Page ' . ($idx + 1) . ' of ' . $totalPages
    ];
}

/* ════════════════════════════════════════════════════════════════
   Assemble the PDF objects, cross-reference table & stream
   ════════════════════════════════════════════════════════════════ */
$objects = [];
function pdfObj(array &$objects, string $body): int {
    $objects[] = $body;
    return count($objects);
}

$fontDefs = [
    'F1' => "<</Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding>>",
    'F2' => "<</Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding>>",
    'F3' => "<</Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding>>",
    'F4' => "<</Type /Font /Subtype /Type1 /BaseFont /Helvetica-BoldOblique /Encoding /WinAnsiEncoding>>"
];
$fontObjIds = [];
foreach ($fontDefs as $fKey => $fBody) {
    if (isset($G->fonts[$fKey])) {
        $fontObjIds[$fKey] = pdfObj($objects, $fBody);
    }
}

$imageObjId = null;
if ($G->logo && file_exists($G->logo['file'])) {
    $rawImg = file_get_contents($G->logo['file']);
    $imageObjId = pdfObj($objects, "<</Type /XObject /Subtype /Image /Width {$G->logo['w']} /Height {$G->logo['h']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($rawImg) . ">>\nstream\n" . $rawImg . "\nendstream");
}

$pageObjIds = [];
foreach ($G->pages as $pIdx => $pData) {
    $ops = $pData['ops'];
    $f = $pData['footer'];
    
    // Footer rules and labels
    $ops .= sprintf('%.2f w 0.886 0.910 0.941 RG %.2f %.2f m %.2f %.2f l S' . "\n", 0.6, $G->margin, $G->H - $f['top'], $G->W - $G->margin, $G->H - $f['top']);
    $ops .= "BT /F1 7.5 Tf 0.392 0.455 0.545 rg " . sprintf('%.2f %.2f Td', $G->margin, $G->H - ($f['top'] + 12)) . " (" . pdfText($f['line1']) . ") Tj ET\n";
    $ops .= "BT /F1 7.5 Tf 0.600 0.650 0.700 rg " . sprintf('%.2f %.2f Td', $G->margin, $G->H - ($f['top'] + 23)) . " (" . pdfText($f['line2']) . ") Tj ET\n";
    $ops .= "BT /F2 8.0 Tf 0.278 0.333 0.412 rg " . sprintf('%.2f %.2f Td', $G->W - $G->margin - 48, $G->H - ($f['top'] + 12)) . " (" . pdfText($f['page']) . ") Tj ET\n";
    
    $stream = "q\n" . $ops . "\nQ";
    $streamLen = strlen($stream);
    $contentId = pdfObj($objects, "<</Length {$streamLen}>>\nstream\n" . $stream . "\nendstream");
    
    $resStr = "<< /Font <<";
    foreach ($fontObjIds as $fn => $fId) {
        $resStr .= " /{$fn} {$fId} 0 R";
    }
    $resStr .= " >>";
    if ($imageObjId) {
        $resStr .= " /XObject << /Im1 {$imageObjId} 0 R >>";
    }
    $resStr .= " >>";
    
    $pageObjIds[] = pdfObj($objects, "<</Type /Page /Parent 2 0 R /MediaBox [0 0 {$G->W} {$G->H}] /Resources {$resStr} /Contents {$contentId} 0 R>>");
}

$pagesKids = implode(' ', array_map(fn($id) => "{$id} 0 R", $pageObjIds));
$pageTreeId = pdfObj($objects, "<</Type /Pages /Kids [{$pagesKids}] /Count " . count($pageObjIds) . ">>");
$catalogId = pdfObj($objects, "<</Type /Catalog /Pages {$pageTreeId} 0 R>>");

$pdfOutput = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $objIdx => $objBody) {
    $offsets[] = strlen($pdfOutput);
    $pdfOutput .= ($objIdx + 1) . " 0 obj\n" . $objBody . "\nendobj\n";
}
$xrefOffset = strlen($pdfOutput);
$pdfOutput .= "xref\n0 " . (count($objects) + 1) . "\n";
$pdfOutput .= "0000000000 65535 f \n";
for ($i = 1; $i <= count($objects); $i++) {
    $pdfOutput .= sprintf("%010d 00000 n \n", $offsets[$i]);
}
$pdfOutput .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root {$catalogId} 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

// Clean output buffer to ensure clean binary delivery
while (ob_get_level()) { ob_end_clean(); }

header('Content-Type: application/pdf');
$isInline = (($_GET['inline'] ?? '') === '1' || ($_GET['view'] ?? '') === '1');
header('Content-Disposition: ' . ($isInline ? 'inline' : 'attachment') . '; filename="' . $doc['doc_number'] . '.pdf"');
header('Content-Length: ' . strlen($pdfOutput));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
echo $pdfOutput;
exit;
