<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoiceDownloadController extends Controller
{
    public function download(Request $request, Invoice $invoice): BinaryFileResponse
    {
        // Ownership is embedded in the signed URL as `cid` — tamper-proof via signature
        if ((int) $request->query('cid') !== $invoice->customer_id) {
            abort(403);
        }

        if (! $invoice->pdf_url) {
            abort(404, 'Invoice PDF is not yet available.');
        }

        $path = storage_path('app/public/' . $invoice->pdf_url);

        if (! file_exists($path)) {
            abort(404, 'Invoice PDF file not found.');
        }

        return response()->download($path, $invoice->invoice_number . '.pdf');
    }
}
