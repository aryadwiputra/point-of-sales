<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\AddCartItemRequest;
use App\Http\Requests\Transaction\CartContextRequest;
use App\Http\Requests\Transaction\ConfirmTransactionPaymentRequest;
use App\Http\Requests\Transaction\HistoryTransactionRequest;
use App\Http\Requests\Transaction\HoldCartRequest;
use App\Http\Requests\Transaction\SearchProductRequest;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateCartItemRequest;
use App\Models\Transaction;
use App\Services\Transactions\AddCartItemService;
use App\Services\Transactions\CartStateService;
use App\Services\Transactions\CheckoutTransactionService;
use App\Services\Transactions\ConfirmTransactionPaymentService;
use App\Services\Transactions\DeleteCartItemService;
use App\Services\Transactions\HeldCartService;
use App\Services\Transactions\SearchTransactionProductService;
use App\Services\Transactions\TransactionHistoryQueryService;
use App\Services\Transactions\TransactionIndexQueryService;
use App\Services\Transactions\TransactionPricingPreviewService;
use App\Services\Transactions\TransactionPrintQueryService;
use App\Services\Transactions\UpdateCartItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request, TransactionIndexQueryService $service): Response
    {
        return Inertia::render('Dashboard/Transactions/Index', $service->execute($request->user()->id));
    }

    public function searchProduct(
        SearchProductRequest $request,
        SearchTransactionProductService $service
    ): JsonResponse {
        $product = $service->execute($request->validated('barcode'));

        return response()->json([
            'success' => $product !== null,
            'data' => $product,
        ]);
    }

    public function previewPricing(
        CartContextRequest $request,
        TransactionPricingPreviewService $service
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $service->execute($request->validated(), $request->user()->id),
        ]);
    }

    public function addToCart(
        AddCartItemRequest $request,
        AddCartItemService $service,
        CartStateService $cartStateService
    ): JsonResponse|RedirectResponse {
        $result = $service->execute($request->validated(), $request->user()->id);

        if (! $result['success']) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], $result['status']);
            }

            return redirect()
                ->back()
                ->with('error', $result['web_message'] ?? $result['message']);
        }

        if ($request->expectsJson()) {
            return response()->json(
                $cartStateService->state($request->user()->id, $request->cartContext(), $result['message'])
            );
        }

        return redirect()->route('transactions.index')->with('success', $result['message']);
    }

    public function destroyCart(
        CartContextRequest $request,
        int|string $cart_id,
        DeleteCartItemService $service,
        CartStateService $cartStateService
    ): JsonResponse|RedirectResponse {
        $result = $service->execute($cart_id, $request->user()->id);

        if (! $result['success']) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], $result['status']);
            }

            return back()->withErrors(['message' => $result['message']]);
        }

        if ($request->expectsJson()) {
            return response()->json(
                $cartStateService->state($request->user()->id, $request->cartContext(), $result['message'])
            );
        }

        return back();
    }

    public function updateCart(
        UpdateCartItemRequest $request,
        int|string $cart_id,
        UpdateCartItemService $service,
        CartStateService $cartStateService
    ): JsonResponse|RedirectResponse {
        $result = $service->execute($cart_id, (float) $request->validated('qty'), $request->user()->id);

        if (! $result['success']) {
            if (! $request->expectsJson()) {
                return back()->withErrors(['message' => $result['message']]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status']);
        }

        if ($request->expectsJson()) {
            return response()->json(
                $cartStateService->state($request->user()->id, $request->cartContext(), $result['message'])
            );
        }

        return back()->with('success', $result['message']);
    }

    public function holdCart(HoldCartRequest $request, HeldCartService $service): JsonResponse|RedirectResponse
    {
        $result = $service->hold($request->user()->id, $request->validated('label'));

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status']);
        }

        return back()->with('success', $result['message']);
    }

    public function resumeCart(Request $request, string $holdId, HeldCartService $service): JsonResponse|RedirectResponse
    {
        $result = $service->resume($request->user()->id, $holdId);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status']);
        }

        return back()->with('success', $result['message']);
    }

    public function clearHold(Request $request, string $holdId, HeldCartService $service): JsonResponse|RedirectResponse
    {
        $result = $service->clear($request->user()->id, $holdId);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ], $result['status']);
        }

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    public function getHeldCarts(Request $request, HeldCartService $service): JsonResponse
    {
        return response()->json($service->list($request->user()->id));
    }

    public function store(
        StoreTransactionRequest $request,
        CheckoutTransactionService $service
    ): RedirectResponse {
        $result = $service->execute($request->validated(), $request->user());

        if (! $result['success']) {
            return redirect()
                ->route('transactions.index')
                ->with('error', $result['error']);
        }

        $redirect = redirect()->route('transactions.print', $result['transaction']->invoice);

        if (isset($result['warning'])) {
            return $redirect->with('error', $result['warning']);
        }

        return $redirect;
    }

    public function print(string $invoice, TransactionPrintQueryService $service): Response
    {
        return Inertia::render('Dashboard/Transactions/Print', [
            'transaction' => $service->execute($invoice),
        ]);
    }

    public function history(HistoryTransactionRequest $request, TransactionHistoryQueryService $service): Response
    {
        return Inertia::render(
            'Dashboard/Transactions/History',
            $service->execute($request->filters(), $request->user())
        );
    }

    public function confirmPayment(
        ConfirmTransactionPaymentRequest $request,
        Transaction $transaction,
        ConfirmTransactionPaymentService $service
    ): RedirectResponse {
        if (! $service->execute($transaction)) {
            return redirect()
                ->back()
                ->with('error', 'Transaksi sudah dibayar.');
        }

        return redirect()
            ->back()
            ->with('success', "Pembayaran untuk invoice {$transaction->invoice} berhasil dikonfirmasi.");
    }
}
