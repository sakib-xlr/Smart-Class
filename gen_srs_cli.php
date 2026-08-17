<?php
/**
 * CLI helper — generates SRS_SmartClass.docx and saves it to disk.
 * Run: C:\xampp\php\php.exe gen_srs_cli.php
 */

// Prevent the original script from streaming to browser
define('GENERATING_DOCX_CLI', true);

// We need to replicate the DOCX generation without the header() calls.
// Include only the helper functions and body-building logic, then save the zip.

if (!class_exists('ZipArchive')) {
    die("ZipArchive is not enabled.\n");
}

// ─── XML ESCAPE ───────────────────────────────────────────────────────────────
function xe(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// ─── PARAGRAPH ────────────────────────────────────────────────────────────────
function para(string $text, bool $bold = false, string $color = '', int $sz = 20, string $align = '', int $spaceAfter = 120, int $indent = 0): string {
    $pPr  = '<w:pStyle w:val="Normal"/>';
    if ($align)  $pPr .= "<w:jc w:val=\"{$align}\"/>";
    if ($indent) $pPr .= "<w:ind w:left=\"{$indent}\"/>";
    $pPr .= "<w:spacing w:after=\"{$spaceAfter}\"/>";
    $rPr = '<w:rPr>';
    if ($bold)  $rPr .= '<w:b/><w:bCs/>';
    if ($color) $rPr .= "<w:color w:val=\"{$color}\"/>";
    $rPr .= "<w:sz w:val=\"{$sz}\"/><w:szCs w:val=\"{$sz}\"/>";
    $rPr .= '</w:rPr>';
    return "<w:p><w:pPr>{$pPr}</w:pPr><w:r>{$rPr}<w:t xml:space=\"preserve\">" . xe($text) . "</w:t></w:r></w:p>";
}

// ─── HEADING ─────────────────────────────────────────────────────────────────
function heading(string $text, int $level): string {
    return "<w:p><w:pPr><w:pStyle w:val=\"Heading{$level}\"/></w:pPr>"
         . "<w:r><w:t xml:space=\"preserve\">" . xe($text) . "</w:t></w:r></w:p>";
}

// ─── PAGE BREAK ───────────────────────────────────────────────────────────────
function pageBreak(): string {
    return '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
}

// ─── BLANK LINE ───────────────────────────────────────────────────────────────
function blank(): string {
    return '<w:p><w:pPr><w:spacing w:after="120"/></w:pPr></w:p>';
}

// ─── BLOCK QUOTE ─────────────────────────────────────────────────────────────
function blockquote(string $text): string {
    $out = '';
    foreach (explode("\n", $text) as $line) {
        if (trim($line) === '') { $out .= blank(); continue; }
        $out .= '<w:p>
          <w:pPr>
            <w:pStyle w:val="Normal"/>
            <w:ind w:left="480"/>
            <w:pBdr><w:left w:val="single" w:sz="18" w:space="12" w:color="4F46E5"/></w:pBdr>
            <w:spacing w:after="80"/>
            <w:shd w:val="clear" w:color="auto" w:fill="EEF2FF"/>
          </w:pPr>
          <w:r>
            <w:rPr><w:i/><w:iCs/><w:color w:val="1E40AF"/><w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr>
            <w:t xml:space="preserve">' . xe($line) . '</w:t>
          </w:r>
        </w:p>';
    }
    return $out;
}

// ─── CODE BLOCK ──────────────────────────────────────────────────────────────
function codeblock(string $text): string {
    $out = '';
    foreach (explode("\n", $text) as $line) {
        $out .= '<w:p>
          <w:pPr>
            <w:pStyle w:val="Normal"/>
            <w:spacing w:before="0" w:after="0"/>
            <w:shd w:val="clear" w:color="auto" w:fill="F1F5F9"/>
            <w:ind w:left="200" w:right="200"/>
          </w:pPr>
          <w:r>
            <w:rPr>
              <w:rFonts w:ascii="Courier New" w:hAnsi="Courier New" w:cs="Courier New"/>
              <w:sz w:val="15"/><w:szCs w:val="15"/>
              <w:color w:val="1E293B"/>
            </w:rPr>
            <w:t xml:space="preserve">' . xe($line !== '' ? $line : ' ') . '</w:t>
          </w:r>
        </w:p>';
    }
    return $out . blank();
}

// ─── TABLE ───────────────────────────────────────────────────────────────────
function tbl(array $headers, array $rows, array $widths = []): string {
    $cols = max(count($headers), count($rows[0] ?? []));
    if (empty($widths)) {
        $w = $cols > 0 ? intdiv(9000, $cols) : 9000;
        $widths = array_fill(0, $cols, $w);
    }
    $xml  = '<w:tbl>';
    $xml .= '<w:tblPr>
      <w:tblStyle w:val="TableGrid"/>
      <w:tblW w:w="9000" w:type="dxa"/>
      <w:tblBorders>
        <w:top    w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>
        <w:left   w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>
        <w:bottom w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>
        <w:right  w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>
        <w:insideH w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>
        <w:insideV w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>
      </w:tblBorders>
      <w:tblCellMar>
        <w:top    w:w="80"  w:type="dxa"/>
        <w:left   w:w="120" w:type="dxa"/>
        <w:bottom w:w="80"  w:type="dxa"/>
        <w:right  w:w="120" w:type="dxa"/>
      </w:tblCellMar>
    </w:tblPr>';
    $xml .= '<w:tblGrid>';
    foreach ($widths as $cw) $xml .= "<w:gridCol w:w=\"{$cw}\"/>";
    $xml .= '</w:tblGrid>';
    if (!empty($headers)) {
        $xml .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>';
        foreach ($headers as $i => $h) {
            $cw   = $widths[$i] ?? 1800;
            $xml .= "<w:tc>
              <w:tcPr>
                <w:tcW w:w=\"{$cw}\" w:type=\"dxa\"/>
                <w:shd w:val=\"clear\" w:color=\"auto\" w:fill=\"1E3A5F\"/>
              </w:tcPr>
              <w:p><w:pPr><w:spacing w:before=\"60\" w:after=\"60\"/></w:pPr>
                <w:r>
                  <w:rPr><w:b/><w:bCs/><w:color w:val=\"FFFFFF\"/><w:sz w:val=\"18\"/><w:szCs w:val=\"18\"/></w:rPr>
                  <w:t xml:space=\"preserve\">" . xe($h) . "</w:t>
                </w:r>
              </w:p>
            </w:tc>";
        }
        $xml .= '</w:tr>';
    }
    foreach ($rows as $ri => $row) {
        $fill = ($ri % 2 === 0) ? 'F8FAFC' : 'FFFFFF';
        $xml .= '<w:tr>';
        foreach ((array)$row as $ci => $cell) {
            $cw       = $widths[$ci] ?? 1800;
            $isBold   = is_array($cell) && !empty($cell['bold']);
            $cellText = is_array($cell) ? ($cell['text'] ?? '') : (string)$cell;
            $xml .= "<w:tc>
              <w:tcPr>
                <w:tcW w:w=\"{$cw}\" w:type=\"dxa\"/>
                <w:shd w:val=\"clear\" w:color=\"auto\" w:fill=\"{$fill}\"/>
              </w:tcPr>
              <w:p><w:pPr><w:spacing w:before=\"60\" w:after=\"60\"/></w:pPr>
                <w:r>
                  <w:rPr><w:sz w:val=\"18\"/><w:szCs w:val=\"18\"/>" . ($isBold ? '<w:b/><w:bCs/>' : '') . "</w:rPr>
                  <w:t xml:space=\"preserve\">" . xe($cellText) . "</w:t>
                </w:r>
              </w:p>
            </w:tc>";
        }
        $xml .= '</w:tr>';
    }
    $xml .= '</w:tbl>';
    $xml .= blank();
    return $xml;
}

// Now include just the $body building and XML structures from generate_srs_docx.php
// by re-running it but capturing everything before the header() calls.
// Strategy: read the file, strip the header()/readfile()/unlink()/exit block, eval it.

$srcFile = __DIR__ . '/generate_srs_docx.php';
$src = file_get_contents($srcFile);

// Remove opening <?php tag
$src = preg_replace('/<\?php\s*/', '', $src, 1);

// Remove the streaming block at end (from "// ─── STREAM FILE TO BROWSER" onward)
$src = preg_replace('/\/\/ ─── STREAM FILE TO BROWSER.*$/s', '', $src);

// Also remove the function definitions already declared above (to avoid redeclaration)
$funcsToRemove = ['xe','para','heading','pageBreak','blank','blockquote','codeblock','tbl'];
foreach ($funcsToRemove as $fn) {
    // Remove function definition blocks
    $src = preg_replace('/\/\/ ─+[^\n]*\n\s*function\s+' . $fn . '\s*\(.*?\)\s*:\s*\w+\s*\{.*?\n\}\n/s', '', $src);
    $src = preg_replace('/function\s+' . $fn . '\s*\(.*?\)\s*:\s*\w+\s*\{.*?\n\}\n/s', '', $src);
}

// Remove ZipArchive check
$src = preg_replace('/if\s*\(!class_exists\(\'ZipArchive\'\)\).*?}\s*/s', '', $src);

// Remove the tmpFile / zip creation / readfile / unlink / exit block
$src = preg_replace('/\$tmpFile\s*=.*?exit;\s*$/s', '', $src);

// Evaluate the cleaned source to get $body, $contentTypes, $relsRoot, $relsDoc, $styles, $settings, $document, $coreXml, $appXml
eval($src);

// Now build the docx
$tmpFile = __DIR__ . '/SRS_SmartClass.docx';
$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Could not create file: $tmpFile\n");
}
$zip->addFromString('[Content_Types].xml',          $contentTypes);
$zip->addFromString('_rels/.rels',                  $relsRoot);
$zip->addFromString('word/document.xml',            $document);
$zip->addFromString('word/_rels/document.xml.rels', $relsDoc);
$zip->addFromString('word/styles.xml',              $styles);
$zip->addFromString('word/settings.xml',            $settings);
$zip->addFromString('docProps/core.xml',            $coreXml);
$zip->addFromString('docProps/app.xml',             $appXml);
$zip->close();

echo "✅ SRS_SmartClass.docx created at: $tmpFile\n";
echo "   Size: " . number_format(filesize($tmpFile)) . " bytes\n";
