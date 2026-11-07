<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Générateur XLSX minimaliste (aucune dépendance externe, basé sur ZipArchive).
 *
 * Supporte plusieurs feuilles, en-têtes stylés, colonnes de montants
 * (format #,##0.00), textes UTF-8 (arabe inclus) et largeurs automatiques.
 */
class XlsxWriter
{
    /**
     * @var array<int, array{name:string, columns:array<int,string>, rows:array<int, array<int, mixed>>, money:array<int,int>}>
     */
    private array $sheets = [];

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, int>  $moneyColumns  index (0-based) des colonnes au format monétaire
     */
    public function addSheet(string $name, array $columns, array $rows, array $moneyColumns = []): static
    {
        $this->sheets[] = [
            'name' => $this->safeSheetName($name),
            'columns' => array_values($columns),
            'rows' => array_values($rows),
            'money' => array_values($moneyColumns),
        ];

        return $this;
    }

    public function title(string $name): static
    {
        return $this->addSheet($name, ['Indicateur', 'Valeur'], []);
    }

    public function save(string $path): void
    {
        if ($this->sheets === []) {
            $this->addSheet('Feuille1', ['—'], []);
        }

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer le fichier XLSX.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->sheetXml($sheet));
        }

        $zip->close();
    }

    private function contentTypes(): string
    {
        $overrides = '';

        foreach ($this->sheets as $index => $sheet) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.($index + 1)
                .'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        $sheets = '';

        foreach ($this->sheets as $index => $sheet) {
            $sheets .= '<sheet name="'.$this->esc($sheet['name']).'" sheetId="'.($index + 1)
                .'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets>'
            .'</workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';

        foreach ($this->sheets as $index => $sheet) {
            $rels .= '<Relationship Id="rId'.($index + 1)
                .'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                .' Target="worksheets/sheet'.($index + 1).'.xml"/>';
        }

        $rels .= '<Relationship Id="rId'.(count($this->sheets) + 1)
            .'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF1E6C8"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    /**
     * @param  array{name:string, columns:array<int,string>, rows:array<int, array<int, mixed>>, money:array<int,int>}  $sheet
     */
    private function sheetXml(array $sheet): string
    {
        $cols = '';
        $sample = array_slice($sheet['rows'], 0, 300);

        foreach ($sheet['columns'] as $index => $column) {
            $width = mb_strlen((string) $column) + 6;

            foreach ($sample as $row) {
                $width = max($width, mb_strlen((string) ($row[$index] ?? '')) + 2);
            }

            $width = (string) min(48, max(10, $width));
            $cols .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        $header = '';
        foreach ($sheet['columns'] as $index => $column) {
            $header .= $this->cell($index, 1, $column, 1, false);
        }

        $body = '';
        foreach ($sheet['rows'] as $rowIndex => $row) {
            $cells = '';

            foreach ($sheet['columns'] as $index => $column) {
                $isMoney = in_array($index, $sheet['money'], true);
                $cells .= $this->cell($index, $rowIndex + 2, $row[$index] ?? null, $isMoney ? 2 : 0, $isMoney);
            }

            $body .= '<row r="'.($rowIndex + 2).'">'.$cells.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0">'
            .'<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .($cols !== '' ? '<cols>'.$cols.'</cols>' : '')
            .'<sheetData>'
            .'<row r="1">'.$header.'</row>'
            .$body
            .'</sheetData>'
            .'</worksheet>';
    }

    private function cell(int $columnIndex, int $rowNumber, mixed $value, int $style, bool $numeric): string
    {
        $ref = $this->colLetter($columnIndex).$rowNumber;
        $styleAttr = $style > 0 ? ' s="'.$style.'"' : '';

        if ($value === null || $value === '') {
            return '<c r="'.$ref.'"'.$styleAttr.'/>';
        }

        if (is_int($value) || is_float($value)) {
            return '<c r="'.$ref.'"'.$styleAttr.'><v>'.(0 + $value).'</v></c>';
        }

        if ($numeric && is_numeric($value)) {
            return '<c r="'.$ref.'"'.$styleAttr.'><v>'.(0 + (float) $value).'</v></c>';
        }

        return '<c r="'.$ref.'" t="inlineStr"'.$styleAttr.'><is><t xml:space="preserve">'
            .$this->esc((string) $value).'</t></is></c>';
    }

    private function colLetter(int $index): string
    {
        $index++;
        $letters = '';

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    private function safeSheetName(string $name): string
    {
        $name = trim(preg_replace('/[\\\\\\/?*\\[\\]:]/u', ' ', $name) ?? $name);
        $name = mb_substr($name !== '' ? $name : 'Feuille', 0, 31);

        $used = array_column($this->sheets, 'name');
        if (in_array($name, $used, true)) {
            $base = mb_substr($name, 0, 27);
            $i = 2;

            while (in_array($base.' '.$i, $used, true)) {
                $i++;
            }

            $name = $base.' '.$i;
        }

        return $name;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
