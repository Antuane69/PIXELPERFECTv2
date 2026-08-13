<?php

namespace App\Services\Reportes\Drivers;

use App\Services\Reportes\ExportConfig;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelDriver
{
    private const HEADER_ROW = 5;

    private const COLOR_PRIMARY = 'FF935AAC';

    private const COLOR_PRIMARY_DARK = 'FF7E4896';

    private const COLOR_PRIMARY_SOFT = 'FFF7EEFA';

    private const COLOR_PRIMARY_SOFTER = 'FFFCF9FE';

    private const COLOR_BORDER = 'FFE6D8EC';

    private const COLOR_FOREGROUND = 'FF271B2C';

    private const COLOR_MUTED_FOREGROUND = 'FF6F5978';

    public function __construct(private readonly ExportConfig $config) {}

    public function download(): BinaryFileResponse
    {
        return $this->downloadRecords($this->config->getData());
    }

    /** @param EloquentBuilder<covariant Model>|QueryBuilder $query */
    public function downloadFromQuery(
        EloquentBuilder|QueryBuilder $query,
        int $chunkSize = 1000,
    ): BinaryFileResponse {
        return $this->downloadRecords($query->lazy(max($chunkSize, 1)));
    }

    /** @param iterable<mixed> $records */
    private function downloadRecords(iterable $records): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setTitle($this->config->getTitle());
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->safeSheetName($this->config->getSheetName()));
        $sheet->setShowGridlines(false);
        $sheet->getTabColor()->setARGB(self::COLOR_PRIMARY);
        $sheet->getSheetView()->setZoomScale(90);
        $this->writeHeader($sheet);

        $rowNumber = self::HEADER_ROW + 1;
        $index = 1;

        foreach ($records as $record) {
            $sheet->fromArray($this->config->formatRow($record, $index++), null, "A{$rowNumber}");
            $rowNumber++;
        }

        $this->styleData($sheet, $rowNumber - 1);
        $this->applyColumnWidths($sheet);
        $sheet->getStyle("A1:{$this->lastColumn()}".max($rowNumber - 1, self::HEADER_ROW))
            ->getFont()
            ->setName('Aptos');
        $sheet->freezePane('A'.(self::HEADER_ROW + 1));
        $sheet->setAutoFilter('A'.self::HEADER_ROW.':'.$this->lastColumn().max($rowNumber - 1, self::HEADER_ROW));

        $filePath = tempnam(sys_get_temp_dir(), 'pixel-perfect-report-');

        if ($filePath === false) {
            throw new RuntimeException('No se pudo crear archivo temporal para exportación.');
        }

        (new Xlsx($spreadsheet))->save($filePath);
        $spreadsheet->disconnectWorksheets();

        return response()->download(
            $filePath,
            $this->config->getFileName().'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend(true);
    }

    private function writeHeader(Worksheet $sheet): void
    {
        $lastColumn = $this->lastColumn();
        $logoPath = $this->config->getLogoPath();

        if ($logoPath !== null) {
            $drawing = new Drawing;
            $drawing->setName('Logo de Pixel Perfect');
            $drawing->setPath($logoPath);
            $drawing->setHeight(55);
            $drawing->setCoordinates('A1');
            $drawing->setWorksheet($sheet);
            $sheet->getRowDimension(1)->setRowHeight(44);
        }

        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', $this->config->getTitle());
        $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => self::COLOR_PRIMARY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells("A3:{$lastColumn}3");
        $sheet->setCellValue('A3', 'Generado: '.now()->format('d/m/Y H:i'));
        $sheet->getStyle("A3:{$lastColumn}3")->applyFromArray([
            'font' => ['color' => ['argb' => self::COLOR_MUTED_FOREGROUND]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);

        $sheet->fromArray($this->config->getHeadings(), null, 'A'.self::HEADER_ROW);
        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(30);
        $sheet->getStyle('A'.self::HEADER_ROW.":{$lastColumn}".self::HEADER_ROW)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_PRIMARY]],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['argb' => self::COLOR_PRIMARY_DARK],
                ],
                'insideVertical' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFAE79C3'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    private function styleData(Worksheet $sheet, int $lastRow): void
    {
        if ($lastRow <= self::HEADER_ROW) {
            return;
        }

        $range = 'A'.self::HEADER_ROW.':'.$this->lastColumn().$lastRow;
        $dataRange = 'A'.(self::HEADER_ROW + 1).':'.$this->lastColumn().$lastRow;
        $sheet->getStyle($range)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()
            ->setARGB(self::COLOR_BORDER);
        $sheet->getStyle($dataRange)->applyFromArray([
            'font' => ['color' => ['argb' => self::COLOR_FOREGROUND]],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::COLOR_PRIMARY_SOFTER],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        for ($row = self::HEADER_ROW + 1; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(25);

            if (($row - self::HEADER_ROW) % 2 !== 0) {
                continue;
            }

            $sheet->getStyle("A{$row}:{$this->lastColumn()}{$row}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB(self::COLOR_PRIMARY_SOFT);
        }
    }

    private function applyColumnWidths(Worksheet $sheet): void
    {
        $offset = $this->config->showsIndex() ? 2 : 1;

        if ($this->config->showsIndex()) {
            $sheet->getColumnDimension('A')->setWidth(8);
        }

        foreach (array_keys($this->config->getColumns()) as $index => $key) {
            $column = $this->columnLetter($index + $offset);
            $configured = $this->config->getColumnWidths()[$key] ?? null;

            if ($configured !== null) {
                $sheet->getColumnDimension($column)->setWidth(
                    min((float) $configured, $this->config->getExcelMaxColumnWidth()),
                );

                continue;
            }

            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function lastColumn(): string
    {
        return $this->columnLetter(max(count($this->config->getHeadings()), 1));
    }

    private function columnLetter(int $column): string
    {
        $letter = '';

        while ($column > 0) {
            $column--;
            $letter = chr(65 + ($column % 26)).$letter;
            $column = intdiv($column, 26);
        }

        return $letter;
    }

    private function safeSheetName(string $name): string
    {
        $name = preg_replace('/[\\\\\/\?\*\[\]:]/', '', $name) ?? '';

        return mb_substr($name !== '' ? $name : 'Datos', 0, 31);
    }
}
