<?php

namespace App\Http\Controllers;

use App\Models\Receivable;
use App\Models\Transaction;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicPortalController extends Controller
{
    public function showTransaction(Request $request, $invoice)
    {
        $transaction = Transaction::where('invoice', $invoice)
            ->where('access_token', $request->token)
            ->with(['details.product', 'customer', 'receivable', 'cashier:id,name'])
            ->firstOrFail();

        return Inertia::render('Public/TransactionDetail', [
            'transaction' => [
                'invoice' => $transaction->invoice,
                'created_at' => $transaction->created_at?->toISOString(),
                'payment_method' => $transaction->payment_method,
                'payment_status' => $transaction->payment_status,
                'grand_total' => (int) $transaction->grand_total,
                'discount' => (int) $transaction->discount,
                'shipping_cost' => (int) $transaction->shipping_cost,
                'tax_total' => (int) $transaction->tax_total,
                'cash' => (int) $transaction->cash,
                'change' => (int) $transaction->change,
                'customer_name' => $transaction->customer?->name ?? 'Umum',
                'cashier_name' => $transaction->cashier?->name ?? '-',
                'details' => $transaction->details->map(fn ($d) => [
                    'product_title' => $d->product?->title ?? '-',
                    'qty' => $d->qty,
                    'price' => (int) $d->price,
                ]),
                'receivable' => $transaction->receivable ? [
                    'id' => $transaction->receivable->id,
                    'total' => (int) $transaction->receivable->total,
                    'paid' => (int) $transaction->receivable->paid,
                    'due_date' => $transaction->receivable->due_date?->toISOString(),
                    'status' => $transaction->receivable->status,
                    'remaining' => max(0, (int) $transaction->receivable->total - (int) $transaction->receivable->paid),
                ] : null,
            ],
            'token' => $request->token,
        ]);
    }

    public function payReceivable(Request $request, Receivable $receivable)
    {
        $transaction = $receivable->transaction;
        abort_if($transaction->access_token !== $request->token, 403);

        $paymentGateway = app(PaymentGatewayManager::class);
        $paymentSetting = \App\Models\PaymentSetting::first();
        $gateway = $paymentSetting?->default_gateway ?? 'midtrans';

        if (! $paymentSetting || ! $paymentSetting->isGatewayReady($gateway)) {
            return back()->with('error', 'Gateway pembayaran belum dikonfigurasi.');
        }

        $response = $paymentGateway->createPayment($transaction, $gateway, $paymentSetting);

        if (isset($response['payment_url'])) {
            $transaction->update([
                'payment_reference' => $response['reference'] ?? null,
                'payment_url' => $response['payment_url'] ?? null,
            ]);
        }

        if (isset($response['payment_url'])) {
            return redirect($response['payment_url']);
        }

        return back()->with('error', 'Gagal memproses pembayaran.');
    }
}
