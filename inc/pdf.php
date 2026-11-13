<?php
/**
 * Tiny dependency-free PDF writer (single page, core fonts) + investment certificate layout.
 */
class MiniPdf {
    private string $c = '';
    public float $w = 595.28, $h = 841.89;
    private static array $hv = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,
        667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,
        556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    private const FONTS = ['F1' => 'Helvetica', 'F2' => 'Helvetica-Bold', 'F3' => 'Times-Italic', 'F4' => 'Times-Bold'];

    private static function hex(string $h): array { $h = ltrim($h, '#'); if (strlen($h) === 3) $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2]; return [hexdec(substr($h,0,2))/255, hexdec(substr($h,2,2))/255, hexdec(substr($h,4,2))/255]; }
    private static function enc(string $s): string {
        $s = str_replace(['₹', '–', '—', '•', '’', '‘', '“', '”', '…'], ['Rs. ', '-', '-', '-', "'", "'", '"', '"', '...'], $s);
        $s = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s) ?: preg_replace('/[^\x20-\x7E]/', '', $s);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }
    public function width(string $s, float $size, string $font = 'F1'): float {
        $s = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', str_replace('₹', 'Rs. ', $s)) ?: $s; $t = 0;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) { $o = ord($s[$i]); $t += ($o >= 32 && $o <= 126) ? self::$hv[$o - 32] : 556; }
        return $t * $size / 1000 * (($font === 'F2' || $font === 'F4') ? 1.06 : ($font === 'F3' ? 0.92 : 1));
    }
    public function rect(float $x, float $y, float $w, float $h, ?string $fill = null, ?string $stroke = null, float $lw = 1): void {
        $yy = $this->h - $y - $h; $o = '';
        if ($fill) { [$r,$g,$b] = self::hex($fill); $o .= sprintf("%.3f %.3f %.3f rg ", $r, $g, $b); }
        if ($stroke) { [$r,$g,$b] = self::hex($stroke); $o .= sprintf("%.3f %.3f %.3f RG %.2f w ", $r, $g, $b, $lw); }
        $this->c .= sprintf("%s%.2f %.2f %.2f %.2f re %s\n", $o, $x, $yy, $w, $h, $fill && $stroke ? 'B' : ($fill ? 'f' : 'S'));
    }
    public function line(float $x1, float $y1, float $x2, float $y2, string $color = '#cccccc', float $lw = 0.8): void {
        [$r,$g,$b] = self::hex($color);
        $this->c .= sprintf("%.3f %.3f %.3f RG %.2f w %.2f %.2f m %.2f %.2f l S\n", $r, $g, $b, $lw, $x1, $this->h - $y1, $x2, $this->h - $y2);
    }
    public function text(float $x, float $y, string $s, float $size = 11, string $font = 'F1', string $color = '#000000', string $align = 'L'): void {
        $wd = $this->width($s, $size, $font);
        if ($align === 'C') $x -= $wd / 2; elseif ($align === 'R') $x -= $wd;
        [$r,$g,$b] = self::hex($color);
        $this->c .= sprintf("BT /%s %.2f Tf %.3f %.3f %.3f rg %.2f %.2f Td (%s) Tj ET\n", $font, $size, $r, $g, $b, $x, $this->h - $y, self::enc($s));
    }
    /** Word-wrapped paragraph; returns the y after the last line. */
    public function para(float $x, float $y, float $maxW, string $s, float $size = 10, string $font = 'F1', string $color = '#333333', float $lead = 1.45): float {
        $line = '';
        foreach (preg_split('/\s+/', trim($s)) as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($this->width($try, $size, $font) > $maxW && $line !== '') { $this->text($x, $y, $line, $size, $font, $color); $y += $size * $lead; $line = $word; }
            else $line = $try;
        }
        if ($line !== '') { $this->text($x, $y, $line, $size, $font, $color); $y += $size * $lead; }
        return $y;
    }
    public function output(): string {
        $o = [];
        $o[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $o[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $fonts = ''; $i = 5; foreach (self::FONTS as $k => $_) { $fonts .= "/$k " . $i++ . ' 0 R '; }
        $o[3] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Contents 4 0 R /Resources << /Font << %s>> >> >>', $this->w, $this->h, $fonts);
        $o[4] = '<< /Length ' . strlen($this->c) . " >>\nstream\n" . $this->c . "endstream";
        $i = 5; foreach (self::FONTS as $name) { $o[$i++] = "<< /Type /Font /Subtype /Type1 /BaseFont /$name /Encoding /WinAnsiEncoding >>"; }
        $pdf = "%PDF-1.4\n"; $off = [];
        foreach ($o as $n => $body) { $off[$n] = strlen($pdf); $pdf .= "$n 0 obj\n$body\nendobj\n"; }
        $x = strlen($pdf); $cnt = count($o) + 1;
        $pdf .= "xref\n0 $cnt\n0000000000 65535 f \n";
        foreach ($off as $ofs) $pdf .= sprintf("%010d 00000 n \n", $ofs);
        return $pdf . "trailer\n<< /Size $cnt /Root 1 0 R >>\nstartxref\n$x\n%%EOF";
    }
}

/** Build the investment certificate PDF for an investment id. Returns binary string. */
function certificate_pdf(array $inv, array $user, array $plan, array $project): string {
    $p = new MiniPdf(); $W = $p->w;
    $dark = setting('dark_color', '#0a1930'); $gold = setting('primary_color', '#c8a24a');
    $p->rect(0, 0, $W, $p->h, '#fbf9f3');
    $p->rect(22, 22, $W - 44, $p->h - 44, null, $gold, 2.5);
    $p->rect(30, 30, $W - 60, $p->h - 60, null, $dark, 0.6);
    $p->rect(30, 30, $W - 60, 118, $dark);
    $p->text($W / 2, 78, strtoupper(setting('site_name')), 24, 'F2', '#ffffff', 'C');
    $p->text($W / 2, 100, 'INVESTMENT CERTIFICATE', 13, 'F1', $gold, 'C');
    $p->text($W / 2, 126, 'Certificate No. ' . ($inv['certificate_no'] ?: '-') . '   |   Date of issue: ' . dt($inv['paid_at'] ?: $inv['created_at'], 'd M Y'), 9.5, 'F1', '#d8deea', 'C');

    $p->text($W / 2, 196, 'This is to certify that', 12, 'F3', '#555555', 'C');
    $p->text($W / 2, 230, $user['name'], 26, 'F4', $dark, 'C');
    $p->line($W / 2 - 150, 240, $W / 2 + 150, 240, $gold, 1.2);
    $p->text($W / 2, 266, 'has invested', 12, 'F3', '#555555', 'C');
    $p->text($W / 2, 304, inr($inv['amount'], 2), 30, 'F2', $gold, 'C');
    $p->text($W / 2, 326, '(' . amount_in_words((float)$inv['amount']) . ')', 10, 'F3', '#555555', 'C');
    $p->text($W / 2, 350, 'in the project  "' . ($project['title'] ?? 'Rental Development') . '"', 12, 'F2', $dark, 'C');

    // details table
    $rows = [
        ['Transaction ID', $inv['txn_id'] ?: ($inv['gateway_payment_id'] ?: '-')],
        ['Investment reference', $inv['ref_no']],
        ['Plan', $plan['name'] . '  (' . rtrim(rtrim(number_format((float)$inv['roi'], 2), '0'), '.') . '% p.a.)'],
        ['Tenure', $inv['tenure_months'] . ' months  -  ' . ucfirst($inv['payout_mode']) . ' payouts'],
        ['Total projected return', inr($inv['expected_return'], 2)],
        ['Maturity date', dt($inv['maturity_date'])],
        ['Project completion', dt($project['completion_date'] ?? setting('completion_date'))],
        ['Payment method', strtoupper((string)$inv['method'])],
        ['Investor email', $user['email']],
    ];
    $x = 70; $y = 388; $rw = $W - 140;
    foreach ($rows as $i => $r) {
        if ($i % 2 === 0) $p->rect($x, $y - 14, $rw, 24, '#f1ede0');
        $p->text($x + 12, $y + 2, $r[0], 10, 'F1', '#666666');
        $p->text($x + $rw - 12, $y + 2, $r[1], 10.5, 'F2', $dark, 'R');
        $y += 24;
    }
    $y += 24;
    $p->text($x, $y, 'SAFETY & ACKNOWLEDGEMENT', 10, 'F2', $gold); $y += 16;
    $y = $p->para($x, $y, $rw, setting('certificate_clause'), 9.5, 'F1', '#444444');
    $y += 6;
    $p->para($x, $y, $rw, 'Keep this certificate and the transaction ID safe. You can verify or re-download it any time from your investor dashboard.', 9, 'F3', '#666666');

    // signature block
    $sy = $p->h - 112;
    $p->line($W - 230, $sy, $W - 70, $sy, $dark, 0.8);
    $p->text($W - 150, $sy + 16, setting('certificate_signatory'), 10.5, 'F2', $dark, 'C');
    $p->text($W - 150, $sy + 30, setting('certificate_signatory_title') . ', ' . setting('site_name'), 9, 'F1', '#666666', 'C');
    $legal = trim(setting('company_legal')); $rera = trim(setting('rera_no'));
    $p->text(70, $sy + 16, 'Computer-generated certificate', 9, 'F3', '#777777');
    if ($legal) $p->text(70, $sy + 30, $legal, 8.5, 'F1', '#777777');
    if ($rera) $p->text(70, $sy + 43, 'RERA: ' . $rera, 8.5, 'F1', '#777777');
    return $p->output();
}

function amount_in_words(float $n): string {
    $n = (int)round($n);
    if ($n === 0) return 'Zero Rupees Only';
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $two = function (int $x) use ($ones, $tens) { return $x < 20 ? $ones[$x] : trim($tens[intdiv($x, 10)] . ' ' . $ones[$x % 10]); };
    $three = function (int $x) use ($two, $ones) { $s = ''; if ($x >= 100) { $s = $ones[intdiv($x, 100)] . ' Hundred '; $x %= 100; } return trim($s . ($x ? $two($x) : '')); };
    $out = '';
    foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$u, $name]) {
        if ($n >= $u) { $out .= $three(intdiv($n, $u)) . " $name "; $n %= $u; }
    }
    if ($n) $out .= $three($n);
    return trim($out) . ' Rupees Only';
}
