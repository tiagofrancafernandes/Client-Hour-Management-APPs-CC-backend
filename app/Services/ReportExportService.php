<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function __construct(
        private ReportService $reportService
    ) {
    }

    public function exportToExcel(array $filters): StreamedResponse
    {
        $entries = $this->reportService->getFilteredEntries($filters)->get();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "report_{$timestamp}.xlsx";

        header('X-Filename: ' . $filename);

        return SimpleExcelWriter::streamDownload($filename)
            ->addHeader([
                'Data',
                'Cliente',
                'Carteira',
                'Tipo',
                'Horas',
                'Título',
                'Descrição',
                'Tags',
            ])
            ->addRows($entries->map(fn ($entry) => [
                $entry->reference_date->format('d/m/Y'),
                $entry->wallet->client->name,
                $entry->wallet->name,
                $entry->hours > 0 ? 'Crédito' : 'Débito',
                number_format(abs((float) $entry->hours), 2, ',', '.'),
                $entry->title,
                $entry->description ?? '',
                $entry->tags->pluck('name')->implode(', '),
            ]))
            ->toBrowser();
    }

    public function exportToPdf(array $filters): Response
    {
        $entries = $this->reportService->getFilteredEntries($filters)->get();
        $summary = $this->reportService->getReportSummary($filters);
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "report_{$timestamp}.pdf";

        $pdf = Pdf::loadView('reports.export', [
            'entries' => $entries,
            'summary' => $summary,
            'filters' => $filters,
            'generatedAt' => now()->format('d/m/Y H:i:s'),
        ]);

        $pdf->setPaper('a4', 'landscape');

        header('X-Filename: ' . $filename);

        return $pdf->download($filename);
    }
}
