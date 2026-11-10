<?php
declare(strict_types=1);

/**
 * Génération PDF sans bibliothèque PHP.
 *
 * Le HTML est écrit dans un fichier temporaire, puis Chrome (ou Edge) en
 * mode headless le convertit en PDF. Aucune dépendance réseau : le document
 * est autonome (CSS inclus) et n'a pas besoin d'être connecté à l'application.
 */

function pdf_browser_path(): ?string
{
    $candidates = [];

    foreach (['ProgramFiles', 'ProgramFiles(x86)', 'LOCALAPPDATA'] as $env) {
        $base = getenv($env);

        if ($base === false || $base === '') {
            continue;
        }

        $candidates[] = $base . '\\Google\\Chrome\\Application\\chrome.exe';
        $candidates[] = $base . '\\Microsoft\\Edge\\Application\\msedge.exe';
    }

    $candidates[] = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
    $candidates[] = 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe';
    $candidates[] = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
    $candidates[] = 'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe';

    foreach (array_unique($candidates) as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

function pdf_available(): bool
{
    return pdf_browser_path() !== null
        && (function_exists('proc_open') || function_exists('exec'));
}

/**
 * Convertit un fichier HTML local en PDF.
 */
function pdf_generate(string $htmlFile, string $pdfFile, int $timeout = 45): bool
{
    $browser = pdf_browser_path();

    if ($browser === null) {
        return false;
    }

    @unlink($pdfFile);

    $profile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gc-pdf-' . bin2hex(random_bytes(6));
    @mkdir($profile, 0775, true);

    $url = 'file:///' . str_replace('\\', '/', ltrim($htmlFile, '\\/'));

    $arguments = [
        '--headless=new',
        '--disable-gpu',
        '--no-sandbox',
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-extensions',
        '--disable-background-networking',
        '--user-data-dir=' . $profile,
        '--print-to-pdf=' . $pdfFile,
        '--print-to-pdf-no-header',
        '--no-pdf-header-footer',
        '--run-all-compositor-stages-before-draw',
        '--virtual-time-budget=4000',
        $url,
    ];

    $success = false;

    if (function_exists('proc_open')) {
        $logOut = $profile . DIRECTORY_SEPARATOR . 'out.log';
        $logErr = $profile . DIRECTORY_SEPARATOR . 'err.log';

        $command = escapeshellarg($browser) . ' ' . implode(' ', array_map('escapeshellarg', $arguments));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $logOut, 'w'],
            2 => ['file', $logErr, 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);

        if (is_resource($process)) {
            if (isset($pipes[0]) && is_resource($pipes[0])) {
                fclose($pipes[0]);
            }

            $deadline = microtime(true) + $timeout;

            while (microtime(true) < $deadline) {
                $status = proc_get_status($process);

                if (! $status['running']) {
                    break;
                }

                usleep(250000);
            }

            $status = proc_get_status($process);

            if ($status['running']) {
                proc_terminate($process);
                usleep(300000);
                proc_terminate($process, 9);
            }

            proc_close($process);

            $success = is_file($pdfFile) && filesize($pdfFile) > 1000;
        }
    }

    if (! $success && ! function_exists('proc_open') && function_exists('exec')) {
        $command = escapeshellarg($browser) . ' '
            . implode(' ', array_map('escapeshellarg', $arguments))
            . ' 2>&1';

        @exec($command, $output, $code);
        $success = is_file($pdfFile) && filesize($pdfFile) > 1000;
    }

    pdf_remove_directory($profile);

    return $success;
}

function pdf_remove_directory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $items = @scandir($directory);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            pdf_remove_directory($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($directory);
}

/**
 * Supprime les fichiers PDF/HTML temporaires trop anciens.
 */
function pdf_cleanup(int $maxAge = 3600): void
{
    $dir = DATA_DIR . DIRECTORY_SEPARATOR . 'tmp';

    if (! is_dir($dir)) {
        return;
    }

    foreach (glob($dir . DIRECTORY_SEPARATOR . 'pdf-*') ?: [] as $file) {
        if (is_file($file) && filemtime($file) < time() - $maxAge) {
            @unlink($file);
        }
    }
}

/**
 * Envoie un document HTML au navigateur sous forme de PDF.
 */
function pdf_stream(string $html, string $filename, string $fallback = 'dashboard'): never
{
    if (! pdf_available()) {
        flash(t('pdf_error'), 'error');
        redirect($fallback);
    }

    $dir = DATA_DIR . DIRECTORY_SEPARATOR . 'tmp';

    if (! is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    pdf_cleanup();

    $base = 'pdf-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $htmlFile = $dir . DIRECTORY_SEPARATOR . $base . '.html';
    $pdfFile = $dir . DIRECTORY_SEPARATOR . $base . '.pdf';

    if (file_put_contents($htmlFile, $html) === false) {
        flash(t('pdf_error'), 'error');
        redirect($fallback);
    }

    $ok = pdf_generate($htmlFile, $pdfFile);

    @unlink($htmlFile);

    if (! $ok) {
        @unlink($pdfFile);
        flash(t('pdf_error'), 'error');
        redirect($fallback);
    }

    $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'document.pdf';

    if (! str_ends_with(strtolower($safe), '.pdf')) {
        $safe .= '.pdf';
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $safe . '"');
    header('Content-Length: ' . filesize($pdfFile));
    header('Cache-Control: private, max-age=0, must-revalidate');

    readfile($pdfFile);
    @unlink($pdfFile);
    exit;
}

/**
 * Enveloppe HTML commune aux documents PDF.
 */
function pdf_document(string $title, string $meta, string $body): string
{
    $locale = locale();
    $dir = is_rtl() ? 'rtl' : 'ltr';
    $shop = setting('shop_name', APP_NAME);
    $address = setting('shop_address');
    $phone = setting('shop_phone');
    $footer = setting('shop_footer', t('thanks'));

    $shopLine = array_filter([$address, $phone !== '' ? 'Tél : ' . $phone : '']);

    $css = <<<'CSS'
@page { size: A4; margin: 13mm 11mm; }
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body {
    font-family: "Segoe UI", "Tahoma", "IBM Plex Sans Arabic", Arial, sans-serif;
    font-size: 11.5px;
    line-height: 1.45;
    color: #16181d;
    background: #fff;
}
h1, h2, h3 { margin: 0; font-weight: 700; }
.head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    border-bottom: 2px solid #a97f35;
    padding-bottom: 10px;
    margin-bottom: 16px;
}
.shop-name { font-size: 20px; font-weight: 800; letter-spacing: -.01em; }
.shop-meta { color: #555; font-size: 10.5px; margin-top: 3px; }
.doc-title { text-align: end; }
.doc-title h1 { font-size: 17px; }
.doc-title .meta { color: #555; font-size: 10.5px; margin-top: 3px; }
[dir="rtl"] .doc-title { text-align: start; }
.section { margin-top: 16px; break-inside: avoid; }
.section h2 { font-size: 12.5px; margin-bottom: 6px; color: #3a3a3a; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 18px; }
.info-grid div { display: flex; justify-content: space-between; gap: 12px; padding: 3px 0; border-bottom: 1px dotted #ddd; }
.info-grid span:first-child { color: #666; }
table { width: 100%; border-collapse: collapse; margin-top: 4px; }
th {
    background: #f4f0e6;
    text-align: start;
    font-size: 9.5px;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #444;
    padding: 6px 7px;
    border-bottom: 1px solid #c9c2b2;
}
td { padding: 6px 7px; border-bottom: 1px solid #e6e6e6; vertical-align: top; }
tbody tr:nth-child(even) td { background: #fbfaf6; }
.num { text-align: end; white-space: nowrap; }
.muted { color: #666; }
.small { font-size: 10px; }
.badge { display: inline-block; padding: 2px 7px; border-radius: 9px; font-size: 9.5px; border: 1px solid #999; }
.totals { width: 270px; margin-inline-start: auto; margin-top: 12px; }
.totals div { display: flex; justify-content: space-between; gap: 18px; padding: 3px 0; }
.totals .grand { border-top: 1.5px solid #222; margin-top: 4px; padding-top: 6px; font-size: 14px; font-weight: 800; }
.kpis { display: flex; gap: 10px; margin: 10px 0 4px; }
.kpi { flex: 1; border: 1px solid #ddd; border-radius: 7px; padding: 8px 10px; }
.kpi span { display: block; color: #666; font-size: 9.5px; text-transform: uppercase; letter-spacing: .04em; }
.kpi strong { display: block; font-size: 15px; margin-top: 2px; }
.footer { margin-top: 22px; border-top: 1px solid #ccc; padding-top: 8px; text-align: center; color: #666; font-size: 10px; }
.empty { padding: 14px; text-align: center; color: #777; border: 1px dashed #ccc; margin-top: 6px; }
CSS;

    $header = '<div class="head">'
        . '<div><div class="shop-name">' . e($shop) . '</div>'
        . ($shopLine ? '<div class="shop-meta">' . e(implode(' · ', $shopLine)) . '</div>' : '')
        . '</div>'
        . '<div class="doc-title"><h1>' . e($title) . '</h1>'
        . ($meta !== '' ? '<div class="meta">' . e($meta) . '</div>' : '')
        . '</div></div>';

    $foot = '<div class="footer">' . e($footer) . ' · ' . e(date('d/m/Y H:i')) . '</div>';

    return '<!doctype html><html lang="' . e($locale) . '" dir="' . $dir . '"><head>'
        . '<meta charset="utf-8"><title>' . e($title) . '</title>'
        . '<style>' . $css . '</style></head><body>'
        . $header . $body . $foot
        . '</body></html>';
}

/**
 * Bouton « PDF » (masqué si Chrome/Edge n'est pas disponible).
 */
function pdf_button(string $type, int $id = 0, array $params = []): void
{
    if (! pdf_available()) {
        return;
    }

    $query = array_merge(['type' => $type], $params);

    if ($id > 0) {
        $query['id'] = $id;
    }

    ?>
    <a class="btn btn-ghost no-print" href="<?= url('pdf', $query) ?>" target="_blank" rel="noopener">
        <?= render_icon('file') ?><?= e(t('pdf')) ?>
    </a>
    <?php
}
