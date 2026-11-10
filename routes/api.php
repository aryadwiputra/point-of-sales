<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\PosApiController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\V1\AdvancedSalesInsightsController as V1AdvancedSalesInsightsController;
use App\Http\Controllers\Api\V1\AuditLogController as V1AuditLogController;
use App\Http\Controllers\Api\V1\BankAccountController as V1BankAccountController;
use App\Http\Controllers\Api\V1\CashierShiftController as V1CashierShiftController;
use App\Http\Controllers\Api\V1\CrmCampaignController as V1CrmCampaignController;
use App\Http\Controllers\Api\V1\CrmReminderController as V1CrmReminderController;
use App\Http\Controllers\Api\V1\CustomerSegmentController as V1CustomerSegmentController;
use App\Http\Controllers\Api\V1\CustomerVoucherController as V1CustomerVoucherController;
use App\Http\Controllers\Api\V1\DineAreaController as V1DineAreaController;
use App\Http\Controllers\Api\V1\DineOrderController as V1DineOrderController;
use App\Http\Controllers\Api\V1\DineTableController as V1DineTableController;
use App\Http\Controllers\Api\V1\DiscountApprovalController as V1DiscountApprovalController;
use App\Http\Controllers\Api\V1\GoodsReceivingController as V1GoodsReceivingController;
use App\Http\Controllers\Api\V1\MemberController as V1MemberController;
use App\Http\Controllers\Api\V1\OutletController as V1OutletController;
use App\Http\Controllers\Api\V1\PayableController as V1PayableController;
use App\Http\Controllers\Api\V1\PaymentSettingController as V1PaymentSettingController;
use App\Http\Controllers\Api\V1\PermissionController as V1PermissionController;
use App\Http\Controllers\Api\V1\PriceListController as V1PriceListController;
use App\Http\Controllers\Api\V1\PricingRuleController as V1PricingRuleController;
use App\Http\Controllers\Api\V1\ProfitReportController as V1ProfitReportController;
use App\Http\Controllers\Api\V1\PurchaseOrderController as V1PurchaseOrderController;
use App\Http\Controllers\Api\V1\ReceivableController as V1ReceivableController;
use App\Http\Controllers\Api\V1\RoleController as V1RoleController;
use App\Http\Controllers\Api\V1\SalesReportController as V1SalesReportController;
use App\Http\Controllers\Api\V1\SalesReturnController as V1SalesReturnController;
use App\Http\Controllers\Api\V1\SettingController as V1SettingController;
use App\Http\Controllers\Api\V1\StockMutationController as V1StockMutationController;
use App\Http\Controllers\Api\V1\StockOpnameController as V1StockOpnameController;
use App\Http\Controllers\Api\V1\StockTransferController as V1StockTransferController;
use App\Http\Controllers\Api\V1\SupplierReturnController as V1SupplierReturnController;
use App\Http\Controllers\Api\V1\TransactionController as V1TransactionController;
use App\Http\Controllers\Api\V1\UnitController as V1UnitController;
use App\Http\Controllers\Api\V1\UserController as V1UserController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes v1
|--------------------------------------------------------------------------
|
| Token-based (Sanctum) REST API. All responses JSON.
| Public: auth endpoints + webhooks. Everything else requires Bearer token.
|
*/

// Public webhooks (payment gateways) — signature verified, no auth
Route::prefix('webhooks')->group(function () {
    Route::post('/midtrans', [PaymentWebhookController::class, 'midtrans'])->name('webhooks.midtrans');
    Route::post('/xendit', [PaymentWebhookController::class, 'xendit'])->name('webhooks.xendit');
});

Route::prefix('v1')->group(function () {
    // Auth (public)
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('api.auth.login');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,10')->name('api.auth.register');
    });

    // Protected endpoints (Sanctum token required)
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
            Route::post('/confirm-password', [AuthController::class, 'confirmPassword'])->name('api.auth.confirm-password');
        });

        // Master data — Sanctum abilities mirror Spatie permission names stamped on the token at login
        Route::apiResource('products', ProductController::class)
            ->middlewareFor(['index', 'show'], 'abilities:products-access')
            ->middlewareFor('store', 'abilities:products-create')
            ->middlewareFor('update', 'abilities:products-edit')
            ->middlewareFor('destroy', 'abilities:products-delete')
            ->names([
                'index' => 'api.products.index',
                'store' => 'api.products.store',
                'show' => 'api.products.show',
                'update' => 'api.products.update',
                'destroy' => 'api.products.destroy',
            ]);

        Route::apiResource('customers', CustomerController::class)
            ->middlewareFor(['index', 'show'], 'abilities:customers-access')
            ->middlewareFor('store', 'abilities:customers-create')
            ->middlewareFor('update', 'abilities:customers-edit')
            ->middlewareFor('destroy', 'abilities:customers-delete')
            ->names([
                'index' => 'api.customers.index',
                'store' => 'api.customers.store',
                'show' => 'api.customers.show',
                'update' => 'api.customers.update',
                'destroy' => 'api.customers.destroy',
            ]);

        Route::apiResource('categories', CategoryController::class)
            ->middlewareFor(['index', 'show'], 'abilities:categories-access')
            ->middlewareFor('store', 'abilities:categories-create')
            ->middlewareFor('update', 'abilities:categories-edit')
            ->middlewareFor('destroy', 'abilities:categories-delete')
            ->names([
                'index' => 'api.categories.index',
                'store' => 'api.categories.store',
                'show' => 'api.categories.show',
                'update' => 'api.categories.update',
                'destroy' => 'api.categories.destroy',
            ]);

        Route::apiResource('warehouses', WarehouseController::class)
            ->middlewareFor(['index', 'show'], 'abilities:warehouses-access')
            ->middlewareFor('store', 'abilities:warehouses-create')
            ->middlewareFor('update', 'abilities:warehouses-update')
            ->middlewareFor('destroy', 'abilities:warehouses-delete')
            ->names([
                'index' => 'api.warehouses.index',
                'store' => 'api.warehouses.store',
                'show' => 'api.warehouses.show',
                'update' => 'api.warehouses.update',
                'destroy' => 'api.warehouses.destroy',
            ]);

        Route::apiResource('suppliers', SupplierController::class)
            ->middlewareFor(['index', 'show'], 'abilities:suppliers-access')
            ->middlewareFor('store', 'abilities:suppliers-access')
            ->middlewareFor('update', 'abilities:suppliers-access')
            ->middlewareFor('destroy', 'abilities:suppliers-access')
            ->names([
                'index' => 'api.suppliers.index',
                'store' => 'api.suppliers.store',
                'show' => 'api.suppliers.show',
                'update' => 'api.suppliers.update',
                'destroy' => 'api.suppliers.destroy',
            ]);

        // POS (mobile kasir) — requires the pos-access ability
        Route::prefix('pos')->middleware('abilities:pos-access')->group(function () {
            // Shift
            Route::get('/shift', [PosApiController::class, 'currentShift'])->name('api.pos.shift');
            Route::post('/shift/open', [PosApiController::class, 'openShift'])->name('api.pos.shift.open');
            Route::post('/shift/close', [PosApiController::class, 'closeShift'])->name('api.pos.shift.close');

            // Products & scan
            Route::get('/products', [PosApiController::class, 'products'])->name('api.pos.products');
            Route::post('/products/scan', [PosApiController::class, 'scan'])->name('api.pos.products.scan');

            // Cart
            Route::get('/cart', [PosApiController::class, 'cart'])->name('api.pos.cart');
            Route::post('/cart', [PosApiController::class, 'addToCart'])->name('api.pos.cart.add');
            Route::put('/cart/{cart}', [PosApiController::class, 'updateCart'])->name('api.pos.cart.update');
            Route::delete('/cart/{cart}', [PosApiController::class, 'removeFromCart'])->name('api.pos.cart.remove');
            Route::delete('/cart', [PosApiController::class, 'clearCart'])->name('api.pos.cart.clear');

            // Hold / resume
            Route::post('/hold', [PosApiController::class, 'holdCart'])->name('api.pos.hold');
            Route::get('/holds', [PosApiController::class, 'heldCarts'])->name('api.pos.holds');
            Route::post('/holds/{holdId}/resume', [PosApiController::class, 'resumeHold'])->name('api.pos.holds.resume');
            Route::delete('/holds/{holdId}', [PosApiController::class, 'deleteHold'])->name('api.pos.holds.delete');

            // Checkout & transactions
            Route::post('/checkout', [PosApiController::class, 'checkout'])->name('api.pos.checkout');
            Route::post('/transactions/sync', [PosApiController::class, 'syncTransactions'])->name('api.pos.transactions.sync');
            Route::get('/transactions', [PosApiController::class, 'transactions'])->name('api.pos.transactions');
            Route::get('/transactions/{transaction}', [PosApiController::class, 'transactionDetail'])->name('api.pos.transactions.show');
        });

        Route::middleware('abilities:transactions-access')->group(function () {
            Route::get('/transactions', [V1TransactionController::class, 'listTransactions'])->name('api.transactions.index');
            Route::get('/transactions/pricing-preview', [V1TransactionController::class, 'previewPricing'])
                ->middleware('active_shift')->name('api.transactions.pricing-preview');
            Route::get('/transactions/{invoice}', [V1TransactionController::class, 'show'])->name('api.transactions.show');
            Route::get('/transactions/{invoice}/status', [V1TransactionController::class, 'status'])->name('api.transactions.status');
            Route::get('/transactions/{invoice}/qr', [V1TransactionController::class, 'qrImage'])->name('api.transactions.qr');
            Route::post('/transactions/{transaction}/confirm-payment', [V1TransactionController::class, 'confirmPayment'])
                ->middleware('api_step_up')->name('api.transactions.confirm-payment');
        });

        Route::middleware('abilities:cashier-shifts-access')->group(function () {
            Route::get('/cashier-shifts', [V1CashierShiftController::class, 'listShifts'])->name('api.cashier-shifts.index');
            Route::post('/cashier-shifts', [V1CashierShiftController::class, 'open'])->name('api.cashier-shifts.store');
            Route::get('/cashier-shifts/{cashierShift}', [V1CashierShiftController::class, 'showShift'])->name('api.cashier-shifts.show');
            Route::post('/cashier-shifts/{cashierShift}/close', [V1CashierShiftController::class, 'closeShift'])
                ->middleware('api_step_up')->name('api.cashier-shifts.close');
            Route::post('/cashier-shifts/{cashierShift}/cash-movements', [V1CashierShiftController::class, 'addCashMovement'])->name('api.cashier-shifts.cash-movements.store');
            Route::get('/cashier-shifts/{cashierShift}/report/{type}', [V1CashierShiftController::class, 'printReport'])->name('api.cashier-shifts.report');
        });

        Route::middleware('abilities:sales-returns-access')->group(function () {
            Route::get('/sales-returns', [V1SalesReturnController::class, 'listReturns'])->name('api.sales-returns.index');
            Route::get('/sales-returns/create/{transaction}', [V1SalesReturnController::class, 'createFor'])->name('api.sales-returns.create');
            Route::get('/sales-returns/{salesReturn}', [V1SalesReturnController::class, 'showReturn'])->name('api.sales-returns.show');
            Route::post('/sales-returns', [V1SalesReturnController::class, 'createReturn'])->name('api.sales-returns.store');
            Route::put('/sales-returns/{salesReturn}', [V1SalesReturnController::class, 'updateReturn'])->name('api.sales-returns.update');
            Route::post('/sales-returns/{salesReturn}/complete', [V1SalesReturnController::class, 'completeReturn'])->name('api.sales-returns.complete');
        });

        Route::middleware('abilities:discounts-approve')->group(function () {
            Route::get('/discount-approvals', [V1DiscountApprovalController::class, 'listPending'])->name('api.discount-approvals.pending');
            Route::post('/discount-approvals/{transaction}/approve', [V1DiscountApprovalController::class, 'approve'])->name('api.discount-approvals.approve');
            Route::post('/discount-approvals/{transaction}/deny', [V1DiscountApprovalController::class, 'deny'])->name('api.discount-approvals.deny');
        });

        // Inventory: stock transfers
        Route::middleware('abilities:stock-transfers-access')->group(function () {
            Route::get('/stock-transfers', [V1StockTransferController::class, 'listTransfers'])->name('api.stock-transfers.index');
            Route::get('/stock-transfers/create', [V1StockTransferController::class, 'createData'])->name('api.stock-transfers.create');
            Route::post('/stock-transfers', [V1StockTransferController::class, 'storeTransfer'])->name('api.stock-transfers.store');
            Route::get('/stock-transfers/{stockTransfer}', [V1StockTransferController::class, 'showTransfer'])->name('api.stock-transfers.show');
            Route::post('/stock-transfers/{stockTransfer}/send', [V1StockTransferController::class, 'sendTransfer'])
                ->middleware('abilities:stock-transfers-send')->name('api.stock-transfers.send');
            Route::post('/stock-transfers/{stockTransfer}/receive', [V1StockTransferController::class, 'receiveTransfer'])
                ->middleware('abilities:stock-transfers-receive')->name('api.stock-transfers.receive');
            Route::post('/stock-transfers/{stockTransfer}/cancel', [V1StockTransferController::class, 'cancelTransfer'])
                ->middleware('abilities:stock-transfers-cancel')->name('api.stock-transfers.cancel');
        });

        // Inventory: stock opnames
        Route::middleware('abilities:stock-opnames-access')->group(function () {
            Route::get('/stock-opnames', [V1StockOpnameController::class, 'listOpnames'])->name('api.stock-opnames.index');
            Route::get('/stock-opnames/create', [V1StockOpnameController::class, 'createData'])->name('api.stock-opnames.create');
            Route::post('/stock-opnames', [V1StockOpnameController::class, 'storeOpname'])->name('api.stock-opnames.store');
            Route::get('/stock-opnames/{stockOpname}', [V1StockOpnameController::class, 'showOpname'])->name('api.stock-opnames.show');
            Route::patch('/stock-opnames/{stockOpname}', [V1StockOpnameController::class, 'updateOpname'])->name('api.stock-opnames.update');
            Route::post('/stock-opnames/{stockOpname}/items', [V1StockOpnameController::class, 'storeOpnameItem'])->name('api.stock-opnames.items.store');
            Route::patch('/stock-opnames/{stockOpname}/items/{item}', [V1StockOpnameController::class, 'updateOpnameItem'])->name('api.stock-opnames.items.update');
            Route::post('/stock-opnames/{stockOpname}/finalize', [V1StockOpnameController::class, 'finalizeOpname'])
                ->middleware('abilities:stock-opnames-finalize')->name('api.stock-opnames.finalize');
        });

        // Inventory: stock mutations (read-only)
        Route::middleware('abilities:stock-mutations-access')->group(function () {
            Route::get('/stock-mutations', [V1StockMutationController::class, 'listMutations'])->name('api.stock-mutations.index');
        });

        // Inventory: purchase orders
        Route::middleware('abilities:purchase-orders-access')->group(function () {
            Route::get('/purchase-orders', [V1PurchaseOrderController::class, 'listOrders'])->name('api.purchase-orders.index');
            Route::get('/purchase-orders/create', [V1PurchaseOrderController::class, 'createData'])->name('api.purchase-orders.create');
            Route::post('/purchase-orders', [V1PurchaseOrderController::class, 'storeOrder'])->name('api.purchase-orders.store');
            Route::get('/purchase-orders/{purchaseOrder}', [V1PurchaseOrderController::class, 'showOrder'])->name('api.purchase-orders.show');
            Route::post('/purchase-orders/{purchaseOrder}/place', [V1PurchaseOrderController::class, 'placeOrderApi'])
                ->middleware('abilities:purchase-orders-update')->name('api.purchase-orders.place');
            Route::post('/purchase-orders/{purchaseOrder}/cancel', [V1PurchaseOrderController::class, 'cancelOrderApi'])
                ->middleware('abilities:purchase-orders-update')->name('api.purchase-orders.cancel');
        });

        // Inventory: goods receivings
        Route::middleware('abilities:goods-receivings-access')->group(function () {
            Route::get('/goods-receivings', [V1GoodsReceivingController::class, 'listReceivings'])->name('api.goods-receivings.index');
            Route::get('/goods-receivings/create', [V1GoodsReceivingController::class, 'createData'])->name('api.goods-receivings.create');
            Route::post('/goods-receivings', [V1GoodsReceivingController::class, 'storeReceiving'])->name('api.goods-receivings.store');
            Route::get('/goods-receivings/{goodsReceiving}', [V1GoodsReceivingController::class, 'showReceiving'])->name('api.goods-receivings.show');
        });

        // Inventory: supplier returns
        Route::middleware('abilities:supplier-returns-access')->group(function () {
            Route::get('/supplier-returns', [V1SupplierReturnController::class, 'listReturns'])->name('api.supplier-returns.index');
            Route::get('/supplier-returns/create', [V1SupplierReturnController::class, 'createData'])->name('api.supplier-returns.create');
            Route::post('/supplier-returns', [V1SupplierReturnController::class, 'storeReturn'])->name('api.supplier-returns.store');
            Route::get('/supplier-returns/{supplierReturn}', [V1SupplierReturnController::class, 'showReturn'])->name('api.supplier-returns.show');
            Route::post('/supplier-returns/{supplierReturn}/complete', [V1SupplierReturnController::class, 'completeReturnApi'])
                ->middleware('abilities:supplier-returns-update')->name('api.supplier-returns.complete');
            Route::post('/supplier-returns/{supplierReturn}/cancel', [V1SupplierReturnController::class, 'cancelReturnApi'])
                ->middleware('abilities:supplier-returns-update')->name('api.supplier-returns.cancel');
        });

        // Finance: receivables (piutang)
        Route::middleware('abilities:receivables-access')->group(function () {
            Route::get('/receivables', [V1ReceivableController::class, 'listReceivables'])->name('api.receivables.index');
            Route::get('/receivables/aging', [V1ReceivableController::class, 'agingSummary'])->name('api.receivables.aging');
            Route::get('/receivables/customer-statement', [V1ReceivableController::class, 'customerStatement'])->name('api.receivables.customer-statement');
            Route::get('/receivables/{receivable}', [V1ReceivableController::class, 'showReceivable'])->name('api.receivables.show');
            Route::patch('/receivables/{receivable}/collection-notes', [V1ReceivableController::class, 'updateCollectionNotes'])->name('api.receivables.collection-notes');
            Route::post('/receivables/{receivable}/pay', [V1ReceivableController::class, 'payReceivable'])
                ->middleware('abilities:receivables-pay')->name('api.receivables.pay');
        });

        // Finance: payables (hutang)
        Route::middleware('abilities:payables-access')->group(function () {
            Route::get('/payables', [V1PayableController::class, 'listPayables'])->name('api.payables.index');
            Route::post('/payables', [V1PayableController::class, 'storePayable'])->name('api.payables.store');
            Route::get('/payables/supplier-statement', [V1PayableController::class, 'supplierStatement'])->name('api.payables.supplier-statement');
            Route::get('/payables/{payable}', [V1PayableController::class, 'showPayable'])->name('api.payables.show');
            Route::post('/payables/{payable}/pay', [V1PayableController::class, 'payPayable'])
                ->middleware('abilities:payables-pay')->name('api.payables.pay');
        });

        // CRM: members
        Route::middleware('abilities:customers-access')->group(function () {
            Route::get('/members', [V1MemberController::class, 'listMembers'])->name('api.members.index');
            Route::get('/members/create', [V1MemberController::class, 'createData'])->middleware('abilities:customers-create')->name('api.members.create');
            Route::get('/members/{member}', [V1MemberController::class, 'showMember'])->name('api.members.show');
            Route::post('/members', [V1MemberController::class, 'storeMember'])->middleware('abilities:customers-create')->name('api.members.store');
            Route::put('/members/{member}', [V1MemberController::class, 'updateMember'])->middleware('abilities:customers-edit')->name('api.members.update');
        });

        // CRM: customer vouchers
        Route::middleware('abilities:customer-vouchers-access')->group(function () {
            Route::get('/customer-vouchers', [V1CustomerVoucherController::class, 'listVouchers'])->name('api.customer-vouchers.index');
            Route::get('/customer-vouchers/create', [V1CustomerVoucherController::class, 'createData'])->middleware('abilities:customer-vouchers-create')->name('api.customer-vouchers.create');
            Route::post('/customer-vouchers', [V1CustomerVoucherController::class, 'storeVoucher'])->middleware('abilities:customer-vouchers-create')->name('api.customer-vouchers.store');
            Route::get('/customer-vouchers/{customerVoucher}', [V1CustomerVoucherController::class, 'showVoucher'])->name('api.customer-vouchers.show');
            Route::put('/customer-vouchers/{customerVoucher}', [V1CustomerVoucherController::class, 'updateVoucher'])->middleware('abilities:customer-vouchers-update')->name('api.customer-vouchers.update');
            Route::delete('/customer-vouchers/{customerVoucher}', [V1CustomerVoucherController::class, 'destroyVoucher'])->middleware('abilities:customer-vouchers-delete')->name('api.customer-vouchers.destroy');
        });

        // CRM: customer segments
        Route::middleware('abilities:customer-segments-access')->group(function () {
            Route::get('/customer-segments', [V1CustomerSegmentController::class, 'listSegments'])->name('api.customer-segments.index');
            Route::post('/customer-segments', [V1CustomerSegmentController::class, 'storeSegment'])->middleware('abilities:customer-segments-create')->name('api.customer-segments.store');
            Route::get('/customer-segments/{customerSegment}', [V1CustomerSegmentController::class, 'showSegment'])->name('api.customer-segments.show');
            Route::put('/customer-segments/{customerSegment}', [V1CustomerSegmentController::class, 'updateSegment'])->middleware('abilities:customer-segments-update')->name('api.customer-segments.update');
            Route::delete('/customer-segments/{customerSegment}', [V1CustomerSegmentController::class, 'destroySegment'])->middleware('abilities:customer-segments-delete')->name('api.customer-segments.destroy');
            Route::post('/customer-segments/{customerSegment}/members', [V1CustomerSegmentController::class, 'addMember'])->middleware('abilities:customer-segments-update')->name('api.customer-segments.members.store');
            Route::delete('/customer-segments/{customerSegment}/members/{customer}', [V1CustomerSegmentController::class, 'removeMember'])->middleware('abilities:customer-segments-update')->name('api.customer-segments.members.destroy');
        });

        // CRM: campaigns
        Route::middleware('abilities:crm-campaigns-access')->group(function () {
            Route::get('/crm-campaigns', [V1CrmCampaignController::class, 'listCampaigns'])->name('api.crm-campaigns.index');
            Route::get('/crm-campaigns/create', [V1CrmCampaignController::class, 'createData'])->middleware('abilities:crm-campaigns-create')->name('api.crm-campaigns.create');
            Route::post('/crm-campaigns', [V1CrmCampaignController::class, 'storeCampaign'])->middleware('abilities:crm-campaigns-create')->name('api.crm-campaigns.store');
            Route::get('/crm-campaigns/{crmCampaign}', [V1CrmCampaignController::class, 'showCampaign'])->name('api.crm-campaigns.show');
            Route::put('/crm-campaigns/{crmCampaign}', [V1CrmCampaignController::class, 'updateCampaign'])->middleware('abilities:crm-campaigns-update')->name('api.crm-campaigns.update');
            Route::delete('/crm-campaigns/{crmCampaign}', [V1CrmCampaignController::class, 'destroyCampaign'])->middleware('abilities:crm-campaigns-delete')->name('api.crm-campaigns.destroy');
            Route::post('/crm-campaigns/{crmCampaign}/process', [V1CrmCampaignController::class, 'processCampaign'])->middleware('abilities:crm-campaigns-update')->name('api.crm-campaigns.process');
            Route::post('/crm-campaigns/{crmCampaign}/cancel', [V1CrmCampaignController::class, 'cancelCampaign'])->middleware('abilities:crm-campaigns-update')->name('api.crm-campaigns.cancel');
            Route::post('/crm-campaigns/share-transaction/{transaction}', [V1CrmCampaignController::class, 'shareTransaction'])->middleware('abilities:crm-campaigns-create')->name('api.crm-campaigns.share-transaction');
            Route::post('/crm-campaigns/share-receivable/{receivable}', [V1CrmCampaignController::class, 'shareReceivable'])->middleware('abilities:crm-campaigns-create')->name('api.crm-campaigns.share-receivable');
        });
        Route::post('/crm-campaign-logs/{log}/mark-sent', [V1CrmCampaignController::class, 'markLogSent'])->middleware('abilities:crm-campaigns-update')->name('api.crm-campaign-logs.mark-sent');
        Route::post('/crm-campaign-logs/{log}/skip', [V1CrmCampaignController::class, 'markLogSkipped'])->middleware('abilities:crm-campaigns-update')->name('api.crm-campaign-logs.skip');

        // CRM: reminders
        Route::middleware('abilities:crm-reminders-access')->group(function () {
            Route::get('/crm-reminders', [V1CrmReminderController::class, 'listReminders'])->name('api.crm-reminders.index');
        });

        // Dine-in: areas
        Route::middleware('abilities:dine-tables-access')->group(function () {
            Route::get('/dine-areas', [V1DineAreaController::class, 'listAreas'])->name('api.dine-areas.index');
            Route::get('/dine-areas/{dineArea}', [V1DineAreaController::class, 'showArea'])->name('api.dine-areas.show');
        });
        Route::post('/dine-areas', [V1DineAreaController::class, 'storeArea'])->middleware('abilities:dine-tables-create')->name('api.dine-areas.store');
        Route::patch('/dine-areas/{dineArea}', [V1DineAreaController::class, 'updateArea'])->middleware('abilities:dine-tables-update')->name('api.dine-areas.update');
        Route::delete('/dine-areas/{dineArea}', [V1DineAreaController::class, 'destroyArea'])->middleware('abilities:dine-tables-delete')->name('api.dine-areas.destroy');

        // Dine-in: tables
        Route::middleware('abilities:dine-tables-access')->group(function () {
            Route::get('/dine-tables', [V1DineTableController::class, 'listTables'])->name('api.dine-tables.index');
            Route::get('/dine-tables/{dineTable}/qr', [V1DineTableController::class, 'qrImage'])->name('api.dine-tables.qr');
        });
        Route::post('/dine-tables', [V1DineTableController::class, 'storeTable'])->middleware('abilities:dine-tables-create')->name('api.dine-tables.store');
        Route::patch('/dine-tables/{dineTable}', [V1DineTableController::class, 'updateTable'])->middleware('abilities:dine-tables-update')->name('api.dine-tables.update');
        Route::delete('/dine-tables/{dineTable}', [V1DineTableController::class, 'destroyTable'])->middleware('abilities:dine-tables-delete')->name('api.dine-tables.destroy');

        // Dine-in: orders
        Route::middleware('abilities:dine-orders-access')->group(function () {
            Route::get('/dine-orders', [V1DineOrderController::class, 'listOrders'])->name('api.dine-orders.index');
        });
        Route::post('/dine-orders/{dineOrder}/accept', [V1DineOrderController::class, 'acceptOrder'])->middleware('abilities:dine-orders-process')->name('api.dine-orders.accept');
        Route::post('/dine-orders/{dineOrder}/reject', [V1DineOrderController::class, 'rejectOrder'])->middleware('abilities:dine-orders-process')->name('api.dine-orders.reject');

        // Admin: permissions
        Route::get('/permissions', [V1PermissionController::class, 'listPermissions'])->middleware('abilities:permissions-access')->name('api.permissions.index');

        // Admin: roles
        Route::get('/roles', [V1RoleController::class, 'listRoles'])->middleware('abilities:roles-access')->name('api.roles.index');
        Route::post('/roles', [V1RoleController::class, 'storeRole'])->middleware(['abilities:roles-create', 'api_step_up'])->name('api.roles.store');
        Route::put('/roles/{role}', [V1RoleController::class, 'updateRole'])->middleware(['abilities:roles-update', 'api_step_up'])->name('api.roles.update');
        Route::delete('/roles/{role}', [V1RoleController::class, 'destroyRole'])->middleware(['abilities:roles-delete', 'api_step_up'])->name('api.roles.destroy');

        // Admin: users
        Route::get('/users', [V1UserController::class, 'listUsers'])->middleware('abilities:users-access')->name('api.users.index');
        Route::get('/users/create', [V1UserController::class, 'createData'])->middleware('abilities:users-create')->name('api.users.create');
        Route::post('/users', [V1UserController::class, 'storeUser'])->middleware(['abilities:users-create', 'api_step_up'])->name('api.users.store');
        Route::get('/users/{user}', [V1UserController::class, 'editData'])->middleware('abilities:users-update')->name('api.users.edit');
        Route::put('/users/{user}', [V1UserController::class, 'updateUser'])->middleware(['abilities:users-update', 'api_step_up'])->name('api.users.update');
        Route::delete('/users/{id}', [V1UserController::class, 'destroyUser'])->middleware(['abilities:users-delete', 'api_step_up'])->name('api.users.destroy');

        // Admin: pricing rules
        Route::middleware('abilities:pricing-rules-access')->group(function () {
            Route::get('/pricing-rules', [V1PricingRuleController::class, 'listRules'])->name('api.pricing-rules.index');
            Route::get('/pricing-rules/create', [V1PricingRuleController::class, 'createData'])->name('api.pricing-rules.create');
            Route::post('/pricing-rules/preview', [V1PricingRuleController::class, 'previewRule'])->name('api.pricing-rules.preview');
            Route::get('/pricing-rules/{pricingRule}', [V1PricingRuleController::class, 'showRule'])->name('api.pricing-rules.show');
        });
        Route::post('/pricing-rules', [V1PricingRuleController::class, 'storeRule'])->middleware('abilities:pricing-rules-create')->name('api.pricing-rules.store');
        Route::put('/pricing-rules/{pricingRule}', [V1PricingRuleController::class, 'updateRule'])->middleware('abilities:pricing-rules-update')->name('api.pricing-rules.update');
        Route::delete('/pricing-rules/{pricingRule}', [V1PricingRuleController::class, 'destroyRule'])->middleware('abilities:pricing-rules-delete')->name('api.pricing-rules.destroy');

        // Admin: payment settings
        Route::get('/settings/payments', [V1PaymentSettingController::class, 'editPaymentSettings'])->middleware('abilities:payment-settings-access')->name('api.settings.payments.show');
        Route::put('/settings/payments', [V1PaymentSettingController::class, 'updatePaymentSettings'])->middleware(['abilities:payment-settings-update', 'api_step_up'])->name('api.settings.payments.update');

        // Admin: store/target/printer/loyalty settings
        Route::middleware('abilities:dashboard-access')->group(function () {
            Route::get('/settings/target', [V1SettingController::class, 'showTarget'])->name('api.settings.target.show');
            Route::post('/settings/target', [V1SettingController::class, 'updateTarget'])->name('api.settings.target.update');
            Route::get('/settings/store', [V1SettingController::class, 'storeProfile'])->name('api.settings.store.show');
            Route::post('/settings/store', [V1SettingController::class, 'updateStoreProfile'])->name('api.settings.store.update');
            Route::get('/settings/printer', [V1SettingController::class, 'showPrinter'])->name('api.settings.printer.show');
            Route::post('/settings/printer', [V1SettingController::class, 'updatePrinter'])->name('api.settings.printer.update');
            Route::get('/settings/loyalty', [V1SettingController::class, 'showLoyalty'])->name('api.settings.loyalty.show');
            Route::post('/settings/loyalty', [V1SettingController::class, 'updateLoyalty'])->name('api.settings.loyalty.update');
        });

        // Admin: whatsapp settings
        Route::middleware('abilities:whatsapp-settings-access')->group(function () {
            Route::get('/settings/whatsapp', [V1SettingController::class, 'showWhatsapp'])->name('api.settings.whatsapp.show');
            Route::get('/settings/whatsapp/status', [V1SettingController::class, 'whatsappStatus'])->name('api.settings.whatsapp.status');
        });
        Route::middleware('abilities:whatsapp-settings-update')->group(function () {
            Route::post('/settings/whatsapp', [V1SettingController::class, 'updateWhatsapp'])->name('api.settings.whatsapp.update');
            Route::post('/settings/whatsapp/test', [V1SettingController::class, 'testWhatsapp'])->name('api.settings.whatsapp.test');
            Route::post('/settings/whatsapp/start', [V1SettingController::class, 'startWhatsapp'])->name('api.settings.whatsapp.start');
            Route::post('/settings/whatsapp/disconnect', [V1SettingController::class, 'disconnectWhatsapp'])->name('api.settings.whatsapp.disconnect');
        });

        // Admin: bank accounts
        Route::get('/settings/bank-accounts', [V1BankAccountController::class, 'listBankAccounts'])->middleware('abilities:payment-settings-access')->name('api.settings.bank-accounts.index');
        Route::post('/settings/bank-accounts', [V1BankAccountController::class, 'storeBankAccount'])->middleware(['abilities:payment-settings-update', 'api_step_up'])->name('api.settings.bank-accounts.store');
        Route::put('/settings/bank-accounts/{bankAccount}', [V1BankAccountController::class, 'updateBankAccount'])->middleware(['abilities:payment-settings-update', 'api_step_up'])->name('api.settings.bank-accounts.update');
        Route::delete('/settings/bank-accounts/{bankAccount}', [V1BankAccountController::class, 'destroyBankAccount'])->middleware(['abilities:payment-settings-update', 'api_step_up'])->name('api.settings.bank-accounts.destroy');
        Route::post('/settings/bank-accounts/{bankAccount}/toggle', [V1BankAccountController::class, 'toggleActiveApi'])->middleware(['abilities:payment-settings-update', 'api_step_up'])->name('api.settings.bank-accounts.toggle');
        Route::post('/settings/bank-accounts/order', [V1BankAccountController::class, 'updateOrder'])->middleware(['abilities:payment-settings-update', 'api_step_up'])->name('api.settings.bank-accounts.order');

        // Admin: price lists
        Route::get('/settings/price-lists', [V1PriceListController::class, 'listPriceLists'])->middleware('abilities:price-lists-access')->name('api.settings.price-lists.index');
        Route::get('/settings/price-lists/{priceList}', [V1PriceListController::class, 'showPriceList'])->middleware('abilities:price-lists-access')->name('api.settings.price-lists.show');
        Route::post('/settings/price-lists', [V1PriceListController::class, 'storePriceList'])->middleware(['abilities:price-lists-create', 'api_step_up'])->name('api.settings.price-lists.store');
        Route::put('/settings/price-lists/{priceList}', [V1PriceListController::class, 'updatePriceList'])->middleware(['abilities:price-lists-update', 'api_step_up'])->name('api.settings.price-lists.update');
        Route::delete('/settings/price-lists/{priceList}', [V1PriceListController::class, 'destroyPriceList'])->middleware(['abilities:price-lists-delete', 'api_step_up'])->name('api.settings.price-lists.destroy');
        Route::patch('/settings/price-lists/{priceList}/items', [V1PriceListController::class, 'updatePriceListItem'])->middleware(['abilities:price-lists-update', 'api_step_up'])->name('api.settings.price-lists.items.update');
        Route::delete('/settings/price-lists/{priceList}/items/{productId}', [V1PriceListController::class, 'destroyItem'])->middleware(['abilities:price-lists-update', 'api_step_up'])->name('api.settings.price-lists.items.destroy');

        // Admin: outlets
        Route::get('/settings/outlets', [V1OutletController::class, 'listOutlets'])->middleware('abilities:outlets-access')->name('api.settings.outlets.index');
        Route::post('/settings/outlets', [V1OutletController::class, 'storeOutlet'])->middleware(['abilities:outlets-create', 'api_step_up'])->name('api.settings.outlets.store');
        Route::put('/settings/outlets/{outlet}', [V1OutletController::class, 'updateOutlet'])->middleware(['abilities:outlets-update', 'api_step_up'])->name('api.settings.outlets.update');
        Route::delete('/settings/outlets/{outlet}', [V1OutletController::class, 'destroyOutlet'])->middleware(['abilities:outlets-delete', 'api_step_up'])->name('api.settings.outlets.destroy');

        // Admin: units
        Route::get('/settings/units', [V1UnitController::class, 'listUnits'])->middleware('abilities:units-access')->name('api.settings.units.index');
        Route::post('/settings/units', [V1UnitController::class, 'storeUnit'])->middleware('abilities:units-create')->name('api.settings.units.store');
        Route::put('/settings/units/{unit}', [V1UnitController::class, 'updateUnit'])->middleware('abilities:units-update')->name('api.settings.units.update');
        Route::delete('/settings/units/{unit}', [V1UnitController::class, 'destroyUnit'])->middleware('abilities:units-delete')->name('api.settings.units.destroy');

        // Admin: audit logs
        Route::get('/audit-logs', [V1AuditLogController::class, 'listAuditLogs'])->middleware('abilities:audit-logs-access')->name('api.audit-logs.index');
        Route::get('/audit-logs/{auditLog}', [V1AuditLogController::class, 'showAuditLog'])->middleware('abilities:audit-logs-access')->name('api.audit-logs.show');

        // Reports
        Route::get('/reports/sales', [V1SalesReportController::class, 'sales'])->middleware('abilities:reports-access')->name('api.reports.sales');
        Route::get('/reports/profits', [V1ProfitReportController::class, 'profits'])->middleware('abilities:profits-access')->name('api.reports.profits');
        Route::get('/reports/insights', [V1AdvancedSalesInsightsController::class, 'insights'])->middleware('abilities:reports-access')->name('api.reports.insights');
    });
});
