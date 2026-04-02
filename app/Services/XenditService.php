<?php

namespace App\Services;

use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;

class XenditService
{
    protected InvoiceApi $invoiceApi;

    public function __construct()
    {
        Configuration::setXenditKey(config('services.xendit.secret_key'));
        $this->invoiceApi = new InvoiceApi();
    }

    /**
     * Create a Xendit invoice.
     *
     * @param array $data [external_id, amount, payer_email, description]
     * @return array{invoice_id: string, invoice_url: string}
     */
    public function createInvoice(array $data): array
    {
        $request = new CreateInvoiceRequest([
            'external_id' => $data['external_id'],
            'amount' => $data['amount'],
            'payer_email' => $data['payer_email'],
            'description' => $data['description'],
            'success_redirect_url' => config('services.xendit.success_redirect_url'),
            'failure_redirect_url' => config('services.xendit.failure_redirect_url'),
            'currency' => 'IDR',
            'invoice_duration' => 86400, // 24 hours
        ]);

        $response = $this->invoiceApi->createInvoice($request);

        return [
            'invoice_id' => $response->getId(),
            'invoice_url' => $response->getInvoiceUrl(),
        ];
    }
}
