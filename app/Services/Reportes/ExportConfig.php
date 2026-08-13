<?php

namespace App\Services\Reportes;

use Illuminate\Support\Collection;

class ExportConfig
{
    private string $title = 'Reporte';

    private ?string $subtitle = null;

    private string $fileName = 'reporte';

    private string $sheetName = 'Datos';

    /** @var array<string, string> */
    private array $columns = [];

    /** @var array<string, callable(mixed, mixed): mixed> */
    private array $formatters = [];

    /** @var array<string, int|float> */
    private array $columnWidths = [];

    private ?float $excelMaxColumnWidth = null;

    /** @var Collection<int, mixed> */
    private Collection $data;

    private bool $showIndex = false;

    /** @var list<string> */
    private array $pdfHtmlColumns = [];

    /** @var array<string, string> */
    private array $pdfColumnWidths = [];

    private bool $fixedPdfTableLayout = false;

    private ?string $logoPath = null;

    private function __construct()
    {
        $this->data = collect();
    }

    public static function make(): self
    {
        return new self;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function subtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function fileName(string $fileName): self
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function sheetName(string $sheetName): self
    {
        $this->sheetName = $sheetName;

        return $this;
    }

    /** @param array<string, string> $columns */
    public function columns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /** @param array<string, callable(mixed, mixed): mixed> $formatters */
    public function formatters(array $formatters): self
    {
        $this->formatters = $formatters;

        return $this;
    }

    /** @param array<string, int|float> $widths */
    public function columnWidths(array $widths): self
    {
        $this->columnWidths = $widths;

        return $this;
    }

    public function excelMaxColumnWidth(?float $width): self
    {
        $this->excelMaxColumnWidth = $width;

        return $this;
    }

    /** @param iterable<mixed> $data */
    public function data(iterable $data): self
    {
        $this->data = $data instanceof Collection ? $data->values() : collect($data)->values();

        return $this;
    }

    public function showIndex(bool $show = true): self
    {
        $this->showIndex = $show;

        return $this;
    }

    /** @param list<string> $columns */
    public function pdfHtmlColumns(array $columns): self
    {
        $this->pdfHtmlColumns = $columns;

        return $this;
    }

    /** @param array<string, string> $widths */
    public function pdfColumnWidths(array $widths): self
    {
        $this->pdfColumnWidths = $widths;

        return $this;
    }

    public function fixedPdfTableLayout(bool $enabled = true): self
    {
        $this->fixedPdfTableLayout = $enabled;

        return $this;
    }

    public function logo(string $path): self
    {
        $this->logoPath = $path;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getSheetName(): string
    {
        return $this->sheetName;
    }

    /** @return array<string, string> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /** @return array<string, int|float> */
    public function getColumnWidths(): array
    {
        return $this->columnWidths;
    }

    public function getExcelMaxColumnWidth(): float
    {
        return $this->excelMaxColumnWidth ?? 45.0;
    }

    /** @return Collection<int, mixed> */
    public function getData(): Collection
    {
        return $this->data;
    }

    public function showsIndex(): bool
    {
        return $this->showIndex;
    }

    public function getLogoPath(): ?string
    {
        $path = $this->logoPath ?? public_path('brand/pixel-perfect-banner.png');

        return is_file($path) ? $path : null;
    }

    public function usesFixedPdfTableLayout(): bool
    {
        return $this->fixedPdfTableLayout;
    }

    /** @return list<int> */
    public function getPdfHtmlColumnIndexes(): array
    {
        $offset = $this->showIndex ? 1 : 0;
        $indexes = [];

        foreach (array_keys($this->columns) as $index => $key) {
            if (in_array($key, $this->pdfHtmlColumns, true)) {
                $indexes[] = $index + $offset;
            }
        }

        return $indexes;
    }

    /** @return list<string|null> */
    public function getPdfColumnWidths(): array
    {
        $widths = $this->showIndex ? [$this->pdfColumnWidths['#'] ?? null] : [];

        foreach (array_keys($this->columns) as $key) {
            $widths[] = $this->pdfColumnWidths[$key] ?? null;
        }

        return $widths;
    }

    /** @return list<string> */
    public function getHeadings(): array
    {
        return [...($this->showIndex ? ['#'] : []), ...array_values($this->columns)];
    }

    /** @return list<list<mixed>> */
    public function getRows(): array
    {
        $rows = [];

        foreach ($this->data as $index => $item) {
            $rows[] = $this->formatRow($item, $index + 1);
        }

        return $rows;
    }

    /** @return list<mixed> */
    public function formatRow(mixed $item, int $index): array
    {
        $row = $this->showIndex ? [$index] : [];

        foreach (array_keys($this->columns) as $key) {
            $raw = data_get($item, $key, '');
            $row[] = isset($this->formatters[$key])
                ? ($this->formatters[$key])($raw, $item)
                : $raw;
        }

        return $row;
    }
}
