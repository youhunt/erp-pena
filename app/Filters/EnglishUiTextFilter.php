<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class EnglishUiTextFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $contentType = strtolower((string) $response->getHeaderLine('Content-Type'));
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return null;
        }

        $body = (string) $response->getBody();
        if ($body === '') {
            return null;
        }

        $replacements = [
            'Pilih / cari data' => 'Select / search data',
            'Pilih / cari ' => 'Select / search ',
            'Upload Ulang' => 'Upload Again',
            'Kolom template:' => 'Template columns:',
            'Periksa hasil validasi. Data belum masuk database sampai tombol' => 'Review the validation result. Data will not be saved until the',
            'ditekan.' => 'button is clicked.',
            'Data belum masuk database sampai tombol' => 'Data will not be saved until the',
            'Upload file, sistem akan melakukan preview dan validasi dulu.' => 'Upload a file to preview and validate it first.',
            'Maksimal 10 MB. Format disarankan .xlsx dari template.' => 'Maximum 10 MB. The recommended format is the downloaded .xlsx template.',
            'Semua import production wajib memakai' => 'All production imports must include',
            'Work Order import cukup header WO saja. BOM dan Routing akan otomatis mengikuti master berdasarkan parent_item_code.' => 'Work Order import only requires the WO header. BOM and Routing will be generated automatically from the master data using parent_item_code.',
            'Work Center tidak memakai line_no.' => 'Work Center import does not use line_no.',
            'BOM dan Routing wajib memakai line_no untuk detail line.' => 'BOM and Routing import require line_no for detail lines.',
            'Masih ada error. Perbaiki file lalu upload ulang. Data tidak akan di-commit sebelum semua baris valid.' => 'Errors still exist. Fix the file and upload it again. Data cannot be committed until all rows are valid.',
            'Semua baris valid. Silakan klik' => 'All rows are valid. Click',
            'untuk menyimpan ke database.' => 'to save the data.',
            'Preview utama Work Order hanya menampilkan' => 'The main Work Order preview only shows the',
            'BOM dan Routing yang akan dibuat otomatis dari master ditampilkan di bagian bawah untuk pengecekan.' => 'BOM and Routing lines that will be generated automatically from master data are shown below for review.',
            'Preview BOM & Routing Otomatis' => 'Automatic BOM & Routing Preview',
            'sekarang?' => 'now?',
            'wajib diisi' => 'is required',
            'tidak ditemukan' => 'not found',
            'harus > 0' => 'must be > 0',
            'dalam group yang sama' => 'in the same group',
            'belum ada di master item' => 'is not registered in item master',
            'bukan draft' => 'is not draft',
            'Preview session sudah habis. Upload ulang file import.' => 'The preview session has expired. Upload the import file again.',
            'BOM tidak ditemukan untuk parent item' => 'BOM was not found for parent item',
            'Isi BOM master dulu atau isi component line di file import.' => 'Create the BOM master first or fill component lines in the import file.',
            'Gunakan file Excel .xlsx, .xls, .csv, .tsv, atau .txt.' => 'Use an Excel .xlsx, .xls, .csv, .tsv, or .txt file.',
            'belum diatur' => 'has not been configured',
            'Nomor final diambil dari Document Numbering saat Save.' => 'The final number is generated from Document Numbering on save.',
            'Kosongkan jika ingin auto fallback.' => 'Leave blank to use the automatic fallback.',
            'Kosongkan untuk nomor otomatis.' => 'Leave blank for automatic numbering.',
            'Location otomatis difilter sesuai warehouse yang dipilih.' => 'Location is automatically filtered by the selected warehouse.',
            'No outstanding invoice found.' => 'No outstanding invoice found.',
        ];

        $body = strtr($body, $replacements);
        $response->setBody($body);

        return null;
    }
}
