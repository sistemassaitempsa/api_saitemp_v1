<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class SalidasNoConformeExport implements FromCollection, WithStyles, ShouldAutoSize, WithHeadings
{

    use Exportable;

    protected $exportData;

    public function __construct($exportData = null)
    {
        $this->exportData = $exportData;
    }



    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->exportData;
    }

    public function headings(): array
    {
        return [
            'id',
            'Cliente',
            'Radicado seiya',
            'Responsable',
            'Descripción novedad',
            'Subsanado',
            'Proceso',
            'Tipo de novedad',
            'Fecha creación',
            'Fecha corrección',
            'Coreeción aplicada',
            'Corrigió novedad',
        ];
    }


    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}
