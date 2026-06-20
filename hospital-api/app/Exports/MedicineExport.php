<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Modules\Pharma\Models\Medicine;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Currency;


class MedicineExport implements FromCollection, WithHeadings, WithEvents, WithCustomStartCell
{
    public array $columns;
    public array $dateRange;

    public function __construct($columns, $dateRange, $pharmaId = null)
    {
        $this->columns = $columns;
        $this->dateRange = $dateRange;
          $this->pharmaId = $pharmaId;
    }

    public function startCell(): string
    {
        return 'A3';
    }

    public function headings(): array
    {
        $customLabels = [
            'name' => 'Medicine Name',
            'dosage' => 'Dosage',
            'form' => 'Form',
            'supplier' => 'Supplier',
            'manufacturer' => 'Manufacturer',
            'expiry_date' => 'Expiry Date',
            'selling_price' => 'Selling Price',
            'quntity' => 'Stock',
            // 'status' => 'Status',
        ];
    
        return array_map(function ($column) use ($customLabels) {
            return $customLabels[$column] ?? ucwords(str_replace('_', ' ', $column));
        }, $this->columns);
    }
    
    public function collection()
    {
        $user = auth()->user();
        $query = Medicine::with(['form', 'supplier', 'manufacturer', 'category']) 
            ->select('medicines.*')
            ->where('expiry_date', '>=', Carbon::today());
    
        if ($user->hasRole('pharma')) {
            $query->where('pharma_id', $user->id); 
        }
    
        if (!empty($this->dateRange[0]) && !empty($this->dateRange[1])) {
            $query->whereBetween('created_at', [$this->dateRange[0], $this->dateRange[1]]);
        }
    
        if (!empty($this->filters)) {
            $filter = $this->filters;
    
            if (!empty($filter['name'])) {
                $query->where('id', $filter['name']);
            }
            if (!empty($filter['form'])) {
                $query->where('form_id', $filter['form']);
            }
            if (!empty($filter['dosage'])) {
                $query->where('id', $filter['dosage']);
            }
            if (!empty($filter['supplier'])) {
                $query->where('supplier_id', $filter['supplier']);
            }
            if (!empty($filter['manufacturer'])) {
                $query->where('manufacturer_id', $filter['manufacturer']);
            }
            if (!empty($filter['batch_no'])) {
                $query->where('id', $filter['batch_no']);
            }
        }
    
        $query->orderByDesc('id');
    
        $medicines = $query->get();
        $data = $medicines->map(function ($row) {
            $selectedData = [];
    
            foreach ($this->columns as $column) {
                switch ($column) {
                    case 'expiry_date':
                        $setting = Setting::where('name', 'date_formate')->first();
                        $dateformate = $setting ? $setting->val : 'Y-m-d';
                        $setting = Setting::where('name', 'time_formate')->first();
                        $timeformate = $setting ? $setting->val : 'h:i A';

                        // Try to convert to Carbon if possible
                        try {
                            $expiry = $row->expiry_date instanceof \Carbon\Carbon
                                ? $row->expiry_date
                                : (!empty($row->expiry_date) ? \Carbon\Carbon::parse($row->expiry_date) : null);
                        } catch (\Exception $e) {
                            $expiry = null;
                        }

                        if ($expiry) {
                            $date = $expiry->format($dateformate);
                            $time = $expiry->format($timeformate);
                        } else {
                            $date = '-';
                            $time = '';
                        }

                        $selectedData[$column] = trim($date . ' ' . $time);
                        break;
                    case 'status':
                        $selectedData[$column] = $row->status ? 'active' : 'inactive';
                        break;
                    case 'form':
                        $selectedData[$column] = optional($row->form)->name ?? '-';
                        break;
                    case 'supplier':
                        $selectedData[$column] = optional($row->supplier)->full_name ?? '-';
                        break;
                    case 'manufacturer':
                        $selectedData[$column] = optional($row->manufacturer)->name ?? '-';
                        break;
                    case 'category':
                        $selectedData[$column] = optional($row->category)->name ?? '-';
                        break;
                    case 'selling_price':
                        $selectedData[$column] = currencyFormat($row->selling_price);
                        break;
                        case 'quntity': 
                            $selectedData[$column] = $row->quntity ?? 0; 
                            break;
                    default:
                        $selectedData[$column] = $row->{$column} ?? '-';
                        break;
                }
            }
    
            return $selectedData;
        });
    
        return collect($data);
    }
    
    
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->columns));

                $sheet->setCellValue('A1', "From Date: {$this->dateRange[0]}");
                $sheet->setCellValue('A2', "To Date: {$this->dateRange[1]}");

                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->mergeCells("A2:{$lastColumn}2");

                $sheet->getStyle('A1:A2')->getFont()->setBold(true);
                $sheet->getStyle('A1:A2')->getFont()->setSize(12);
            },
        ];
    }
}
