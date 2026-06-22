<?php

use App\Http\Controllers\Apps\AuditLogController;
use App\Http\Controllers\Apps\CashierShiftController;
use App\Http\Controllers\Apps\CategoryController;
use App\Http\Controllers\Apps\CrmCampaignController;
use App\Http\Controllers\Apps\CrmReminderController;
use App\Http\Controllers\Apps\CustomerController;
use App\Http\Controllers\Apps\CustomerSegmentController;
use App\Http\Controllers\Apps\CustomerVoucherController;
use App\Http\Controllers\Apps\GoodsReceivingController;
use App\Http\Controllers\Apps\ImportExportController;
use App\Http\Controllers\Apps\DiscountApprovalController;
use App\Http\Controllers\Apps\MemberController;
use App\Http\Controllers\Apps\PaymentSettingController;
use App\Http\Controllers\Apps\PricingRuleController;
use App\Http\Controllers\Apps\ProductController;
use App\Http\Controllers\Apps\PurchaseOrderController;
use App\Http\Controllers\Apps\SalesReturnController;
use App\Http\Controllers\Apps\StockMutationController;
use App\Http\Controllers\Apps\StockOpnameController;
use App\Http\Controllers\Apps\StockTransferController;
use App\Http\Controllers\Apps\SupplierReturnController;
use App\Http\Controllers\Apps\TransactionController;
use App\Http\Controllers\Apps\WarehouseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reports\AdvancedSalesInsightsController;
use App\Http\Controllers\Reports\ProfitReportController;
use App\Http\Controllers\Reports\SalesReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => config('security.auth.public_registration'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard/access', function () {
    return Inertia::render('Dashboard/Access');
})->middleware(['auth', 'verified'])->name('dashboard.access');

// Public share routes (no login)
Route::get('/share/transactions/{invoice}', [\App\Http\Controllers\DocumentController::class, 'publicInvoice'])
    ->name('transactions.public');

Route::group(['prefix' => 'dashboard', 'middleware' => ['auth', 'verified']], function () {
    Route::get('/', [DashboardController::class, 'index'])->middleware(['auth', 'verified', 'permission:dashboard-access'])->name('dashboard');
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permissions-access')->name('permissions.index');
    // roles route
    Route::resource('/roles', RoleController::class)
        ->except(['create', 'edit', 'show'])
        ->middlewareFor('index', 'permission:roles-access')
        ->middlewareFor('store', ['permission:roles-create', 'step_up'])
        ->middlewareFor('update', ['permission:roles-update', 'step_up'])
        ->middlewareFor('destroy', ['permission:roles-delete', 'step_up']);
    // users route
    Route::resource('/users', UserController::class)
        ->except('show')
        ->middlewareFor('index', 'permission:users-access')
        ->middlewareFor(['create', 'store'], 'permission:users-create')
        ->middlewareFor('store', ['permission:users-create', 'step_up'])
        ->middlewareFor(['edit', 'update'], 'permission:users-update')
        ->middlewareFor('update', ['permission:users-update', 'step_up'])
        ->middlewareFor('destroy', ['permission:users-delete', 'step_up']);
    Route::post('/notifications/low-stock/read', [NotificationController::class, 'markLowStockRead'])->name('notifications.stock.read');
    Route::post('/notifications/low-stock/read-all', [NotificationController::class, 'markAllLowStockRead'])->name('notifications.stock.readAll');
    Route::get('/regions/regencies', [\App\Http\Controllers\RegionController::class, 'regencies'])->name('regions.regencies');
    Route::get('/regions/districts', [\App\Http\Controllers\RegionController::class, 'districts'])->name('regions.districts');
    Route::get('/regions/villages', [\App\Http\Controllers\RegionController::class, 'villages'])->name('regions.villages');

    Route::resource('categories', CategoryController::class)
        ->middlewareFor(['index', 'show'], 'permission:categories-access')
        ->middlewareFor(['create', 'store'], 'permission:categories-create')
        ->middlewareFor(['edit', 'update'], 'permission:categories-edit')
        ->middlewareFor('destroy', 'permission:categories-delete');
    Route::resource('products', ProductController::class)
        ->middlewareFor(['index', 'show'], 'permission:products-access')
        ->middlewareFor(['create', 'store'], 'permission:products-create')
        ->middlewareFor(['edit', 'update'], 'permission:products-edit')
        ->middlewareFor('destroy', 'permission:products-delete');

    // import/export
    Route::get('/export/products', [ImportExportController::class, 'exportProducts'])->middleware('permission:products-export')->name('export.products');
    Route::get('/export/customers', [ImportExportController::class, 'exportCustomers'])->middleware('permission:customers-export')->name('export.customers');
    Route::get('/export/transactions', [ImportExportController::class, 'exportTransactions'])->middleware('permission:transactions-access')->name('export.transactions');
    Route::post('/import/products', [ImportExportController::class, 'importProducts'])->middleware('permission:products-import')->name('import.products');
    Route::post('/import/customers', [ImportExportController::class, 'importCustomers'])->middleware('permission:customers-import')->name('import.customers');
    Route::get('/import/template/{type}', [ImportExportController::class, 'downloadTemplate'])->name('import.template');
    Route::resource('pricing-rules', PricingRuleController::class)
        ->except(['show'])
        ->middlewareFor('index', 'permission:pricing-rules-access')
        ->middlewareFor(['create', 'store'], 'permission:pricing-rules-create')
        ->middlewareFor(['edit', 'update'], 'permission:pricing-rules-update')
        ->middlewareFor('destroy', 'permission:pricing-rules-delete');
    Route::post('pricing-rules/preview', [PricingRuleController::class, 'preview'])
        ->middleware('permission:pricing-rules-access')
        ->name('pricing-rules.preview');
    Route::get('stock-opnames', [StockOpnameController::class, 'index'])->middleware('permission:stock-opnames-access')->name('stock-opnames.index');
    Route::get('stock-opnames/create', [StockOpnameController::class, 'create'])->middleware('permission:stock-opnames-create')->name('stock-opnames.create');
    Route::post('stock-opnames', [StockOpnameController::class, 'store'])->middleware('permission:stock-opnames-create')->name('stock-opnames.store');
    Route::get('stock-opnames/{stockOpname}', [StockOpnameController::class, 'show'])->middleware('permission:stock-opnames-access')->name('stock-opnames.show');
    Route::patch('stock-opnames/{stockOpname}', [StockOpnameController::class, 'update'])->middleware('permission:stock-opnames-create')->name('stock-opnames.update');
    Route::post('stock-opnames/{stockOpname}/items', [StockOpnameController::class, 'storeItem'])->middleware('permission:stock-opnames-create')->name('stock-opnames.items.store');
    Route::patch('stock-opnames/{stockOpname}/items/{item}', [StockOpnameController::class, 'updateItem'])->middleware('permission:stock-opnames-create')->name('stock-opnames.items.update');
    Route::post('stock-opnames/{stockOpname}/finalize', [StockOpnameController::class, 'finalize'])->middleware('permission:stock-opnames-finalize')->name('stock-opnames.finalize');
    Route::get('stock-mutations', [StockMutationController::class, 'index'])->middleware('permission:stock-mutations-access')->name('stock-mutations.index');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit-logs-access')->name('audit-logs.index');
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->middleware('permission:audit-logs-access')->name('audit-logs.show');
    Route::get('cashier-shifts', [CashierShiftController::class, 'index'])->middleware('permission:cashier-shifts-access')->name('cashier-shifts.index');
    Route::post('cashier-shifts', [CashierShiftController::class, 'store'])->middleware('permission:cashier-shifts-open')->name('cashier-shifts.store');
    Route::get('cashier-shifts/{cashierShift}', [CashierShiftController::class, 'show'])->middleware('permission:cashier-shifts-access')->name('cashier-shifts.show');
    Route::post('cashier-shifts/{cashierShift}/close', [CashierShiftController::class, 'close'])->middleware('permission:cashier-shifts-close')->name('cashier-shifts.close');
    Route::resource('customers', CustomerController::class)
        ->middlewareFor(['index', 'show'], 'permission:customers-access')
        ->middlewareFor(['create', 'store'], 'permission:customers-create')
        ->middlewareFor(['edit', 'update'], 'permission:customers-edit')
        ->middlewareFor('destroy', 'permission:customers-delete');
    Route::resource('members', MemberController::class)
        ->parameters(['members' => 'member'])
        ->except(['destroy'])
        ->middlewareFor(['index', 'show'], 'permission:customers-access')
        ->middlewareFor(['create', 'store'], 'permission:customers-create')
        ->middlewareFor(['edit', 'update'], 'permission:customers-edit');
    Route::resource('customer-vouchers', CustomerVoucherController::class)
        ->except(['show'])
        ->middlewareFor('index', 'permission:customer-vouchers-access')
        ->middlewareFor(['create', 'store'], 'permission:customer-vouchers-create')
        ->middlewareFor(['edit', 'update'], 'permission:customer-vouchers-update')
        ->middlewareFor('destroy', 'permission:customer-vouchers-delete');
    Route::resource('customer-segments', CustomerSegmentController::class)
        ->middlewareFor(['index', 'show'], 'permission:customer-segments-access')
        ->middlewareFor(['create', 'store'], 'permission:customer-segments-create')
        ->middlewareFor(['edit', 'update'], 'permission:customer-segments-update')
        ->middlewareFor('destroy', 'permission:customer-segments-delete');
    Route::post('customer-segments/{customerSegment}/members', [CustomerSegmentController::class, 'storeMember'])
        ->middleware('permission:customer-segments-update')
        ->name('customer-segments.members.store');
    Route::delete('customer-segments/{customerSegment}/members/{customer}', [CustomerSegmentController::class, 'destroyMember'])
        ->middleware('permission:customer-segments-update')
        ->name('customer-segments.members.destroy');
    Route::resource('crm-campaigns', CrmCampaignController::class)
        ->middlewareFor(['index', 'show'], 'permission:crm-campaigns-access')
        ->middlewareFor(['create', 'store'], 'permission:crm-campaigns-create')
        ->middlewareFor(['edit', 'update'], 'permission:crm-campaigns-update')
        ->middlewareFor('destroy', 'permission:crm-campaigns-delete');
    Route::post('crm-campaigns/{crmCampaign}/process', [CrmCampaignController::class, 'process'])
        ->middleware('permission:crm-campaigns-update')
        ->name('crm-campaigns.process');
    Route::post('crm-campaigns/{crmCampaign}/cancel', [CrmCampaignController::class, 'cancel'])
        ->middleware('permission:crm-campaigns-update')
        ->name('crm-campaigns.cancel');
    Route::post('crm-campaign-logs/{log}/mark-sent', [CrmCampaignController::class, 'markLogSent'])
        ->middleware('permission:crm-campaigns-update')
        ->name('crm-campaign-logs.mark-sent');
    Route::post('crm-campaign-logs/{log}/skip', [CrmCampaignController::class, 'markLogSkipped'])
        ->middleware('permission:crm-campaigns-update')
        ->name('crm-campaign-logs.skip');
    Route::get('crm-reminders', [CrmReminderController::class, 'index'])
        ->middleware('permission:crm-reminders-access')
        ->name('crm-reminders.index');

    // route customer history
    Route::get('/customers/{customer}/history', [CustomerController::class, 'getHistory'])->middleware('permission:transactions-access')->name('customers.history');
    Route::put('/customers/{customer}/segments', [CustomerController::class, 'syncSegments'])->middleware('permission:customers-edit')->name('customers.segments.sync');
    Route::match(['post', 'put'], '/customers/{customer}/upgrade-member', [CustomerController::class, 'upgradeToMember'])
        ->middleware('permission:customers-edit')
        ->name('customers.upgrade-member');

    // route customer store via AJAX (no redirect)
    Route::post('/customers/store-ajax', [CustomerController::class, 'storeAjax'])->middleware('permission:customers-create')->name('customers.storeAjax');

    // route transaction
    Route::get('/transactions', [TransactionController::class, 'index'])->middleware('permission:transactions-access')->name('transactions.index');

    // route transaction searchProduct
    Route::post('/transactions/searchProduct', [TransactionController::class, 'searchProduct'])->middleware(['permission:transactions-access', 'active_shift'])->name('transactions.searchProduct');

    // route transaction addToCart
    Route::post('/transactions/addToCart', [TransactionController::class, 'addToCart'])->middleware(['permission:transactions-access', 'active_shift'])->name('transactions.addToCart');

    // route transaction destroyCart
    Route::delete('/transactions/{cart_id}/destroyCart', [TransactionController::class, 'destroyCart'])->middleware(['permission:transactions-access', 'active_shift'])->name('transactions.destroyCart');

    // route transaction updateCart
    Route::patch('/transactions/{cart_id}/updateCart', [TransactionController::class, 'updateCart'])->middleware(['permission:transactions-access', 'active_shift'])->name('transactions.updateCart');
    Route::post('/transactions/pricing-preview', [TransactionController::class, 'previewPricing'])->middleware(['permission:transactions-access', 'active_shift'])->name('transactions.pricing-preview');

    // route hold transaction
    Route::post('/transactions/hold', [TransactionController::class, 'holdCart'])->middleware(['permission:transactions-access', 'active_shift'])->name('transactions.hold');
    Route::post('/transactions/{holdId}/resume', [TransactionController::class, 'resumeCart'])->middleware(['permission:transactions-access', 'active_shift'])->name('transactions.resume');
    Route::delete('/transactions/{holdId}/clearHold', [TransactionController::class, 'clearHold'])->middleware(['permission:transactions-access', 'active_shift'])->name('transactions.clearHold');
    Route::get('/transactions/held', [TransactionController::class, 'getHeldCarts'])->middleware(['permission:transactions-access', 'active_shift'])->name('transactions.held');

    // route transaction store
    Route::post('/transactions/store', [TransactionController::class, 'store'])->middleware(['permission:transactions-access', 'active_shift'])->name('transactions.store');
    Route::get('/transactions/{invoice}/print', [TransactionController::class, 'print'])->middleware('permission:transactions-access')->name('transactions.print');
    Route::get('/transactions/history', [TransactionController::class, 'history'])->middleware('permission:transactions-access')->name('transactions.history');
    Route::post('/transactions/{transaction}/share-campaign', [CrmCampaignController::class, 'shareTransaction'])->middleware('permission:crm-campaigns-create')->name('transactions.share-campaign');
    Route::get('/transactions/history/{transaction}/sales-return/create', [SalesReturnController::class, 'create'])->middleware('permission:sales-returns-create')->name('sales-returns.create');
    Route::post('/transactions/history/{transaction}/sales-return', [SalesReturnController::class, 'store'])->middleware('permission:sales-returns-create')->name('sales-returns.store');
    Route::get('/sales-returns', [SalesReturnController::class, 'index'])->middleware('permission:sales-returns-access')->name('sales-returns.index');
    Route::get('/sales-returns/{salesReturn}', [SalesReturnController::class, 'show'])->middleware('permission:sales-returns-access')->name('sales-returns.show');
    Route::patch('/sales-returns/{salesReturn}', [SalesReturnController::class, 'update'])->middleware('permission:sales-returns-create')->name('sales-returns.update');
    Route::post('/sales-returns/{salesReturn}/complete', [SalesReturnController::class, 'complete'])->middleware('permission:sales-returns-complete')->name('sales-returns.complete');
    // route purchase orders
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('permission:purchase-orders-access')->name('purchase-orders.index');
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->middleware('permission:purchase-orders-create')->name('purchase-orders.create');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('permission:purchase-orders-create')->name('purchase-orders.store');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('permission:purchase-orders-access')->name('purchase-orders.show');
    Route::post('/purchase-orders/{purchaseOrder}/place', [PurchaseOrderController::class, 'placeOrder'])->middleware('permission:purchase-orders-update')->name('purchase-orders.place');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:purchase-orders-update')->name('purchase-orders.cancel');

    // route goods receivings
    Route::get('/goods-receivings', [GoodsReceivingController::class, 'index'])->middleware('permission:goods-receivings-access')->name('goods-receivings.index');
    Route::get('/goods-receivings/create', [GoodsReceivingController::class, 'create'])->middleware('permission:goods-receivings-create')->name('goods-receivings.create');
    Route::post('/goods-receivings', [GoodsReceivingController::class, 'store'])->middleware('permission:goods-receivings-create')->name('goods-receivings.store');
    Route::get('/goods-receivings/{goodsReceiving}', [GoodsReceivingController::class, 'show'])->middleware('permission:goods-receivings-access')->name('goods-receivings.show');

    // route stock transfers
    Route::get('/stock-transfers', [StockTransferController::class, 'index'])->middleware('permission:stock-transfers-access')->name('stock-transfers.index');
    Route::get('/stock-transfers/create', [StockTransferController::class, 'create'])->middleware('permission:stock-transfers-create')->name('stock-transfers.create');
    Route::post('/stock-transfers', [StockTransferController::class, 'store'])->middleware('permission:stock-transfers-create')->name('stock-transfers.store');
    Route::get('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])->middleware('permission:stock-transfers-access')->name('stock-transfers.show');
    Route::post('/stock-transfers/{stockTransfer}/send', [StockTransferController::class, 'send'])->middleware('permission:stock-transfers-send')->name('stock-transfers.send');
    Route::post('/stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])->middleware('permission:stock-transfers-receive')->name('stock-transfers.receive');
    Route::post('/stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])->middleware('permission:stock-transfers-cancel')->name('stock-transfers.cancel');

    // route supplier returns
    Route::get('/supplier-returns', [SupplierReturnController::class, 'index'])->middleware('permission:supplier-returns-access')->name('supplier-returns.index');
    Route::get('/supplier-returns/create', [SupplierReturnController::class, 'create'])->middleware('permission:supplier-returns-create')->name('supplier-returns.create');
    Route::post('/supplier-returns', [SupplierReturnController::class, 'store'])->middleware('permission:supplier-returns-create')->name('supplier-returns.store');
    Route::get('/supplier-returns/{supplierReturn}', [SupplierReturnController::class, 'show'])->middleware('permission:supplier-returns-access')->name('supplier-returns.show');
    Route::post('/supplier-returns/{supplierReturn}/complete', [SupplierReturnController::class, 'complete'])->middleware('permission:supplier-returns-update')->name('supplier-returns.complete');
    Route::post('/supplier-returns/{supplierReturn}/cancel', [SupplierReturnController::class, 'cancel'])->middleware('permission:supplier-returns-update')->name('supplier-returns.cancel');

    // receivables (nota barang)
    Route::get('/receivables', [\App\Http\Controllers\Apps\ReceivableController::class, 'index'])->middleware('permission:receivables-access')->name('receivables.index');
    Route::get('/receivables/aging', [\App\Http\Controllers\Apps\ReceivableController::class, 'aging'])->middleware('permission:receivables-access')->name('receivables.aging');
    Route::get('/receivables/customer-statement', [\App\Http\Controllers\Apps\ReceivableController::class, 'customerStatement'])->middleware('permission:receivables-access')->name('receivables.customer-statement');
    Route::get('/receivables/{receivable}', [\App\Http\Controllers\Apps\ReceivableController::class, 'show'])->middleware('permission:receivables-access')->name('receivables.show');
    Route::patch('/receivables/{receivable}/collection-notes', [\App\Http\Controllers\Apps\ReceivableController::class, 'updateCollectionNotes'])->middleware('permission:receivables-access')->name('receivables.collection-notes');
    Route::post('/receivables/{receivable}/pay', [\App\Http\Controllers\Apps\ReceivableController::class, 'pay'])->middleware('permission:receivables-pay')->name('receivables.pay');
    Route::post('/receivables/{receivable}/share-campaign', [CrmCampaignController::class, 'shareReceivable'])->middleware('permission:crm-campaigns-create')->name('receivables.share-campaign');
    // suppliers & payables
    Route::get('/suppliers', [\App\Http\Controllers\Apps\SupplierController::class, 'index'])->middleware('permission:suppliers-access')->name('suppliers.index');
    Route::post('/suppliers', [\App\Http\Controllers\Apps\SupplierController::class, 'store'])->middleware('permission:suppliers-access')->name('suppliers.store');
    Route::put('/suppliers/{supplier}', [\App\Http\Controllers\Apps\SupplierController::class, 'update'])->middleware('permission:suppliers-access')->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [\App\Http\Controllers\Apps\SupplierController::class, 'destroy'])->middleware('permission:suppliers-access')->name('suppliers.destroy');
    Route::get('/payables', [\App\Http\Controllers\Apps\PayableController::class, 'index'])->middleware('permission:payables-access')->name('payables.index');
    Route::post('/payables', [\App\Http\Controllers\Apps\PayableController::class, 'store'])->middleware('permission:payables-access')->name('payables.store');
    Route::get('/payables/supplier-statement', [\App\Http\Controllers\Apps\PayableController::class, 'supplierStatement'])->middleware('permission:payables-access')->name('payables.supplier-statement');
    Route::get('/payables/{payable}', [\App\Http\Controllers\Apps\PayableController::class, 'show'])->middleware('permission:payables-access')->name('payables.show');
    Route::post('/payables/{payable}/pay', [\App\Http\Controllers\Apps\PayableController::class, 'pay'])->middleware('permission:payables-pay')->name('payables.pay');

    // pdf documents
    Route::get('/documents/transactions/{invoice}/pdf/invoice', [\App\Http\Controllers\DocumentController::class, 'invoice'])->middleware('permission:transactions-access')->name('pdf.transactions.invoice');
    Route::get('/documents/transactions/{invoice}/pdf/receipt/{size?}', [\App\Http\Controllers\DocumentController::class, 'receipt'])->middleware('permission:transactions-access')->name('pdf.transactions.receipt');
    Route::get('/documents/transactions/{invoice}/pdf/shipping', [\App\Http\Controllers\DocumentController::class, 'shipping'])->middleware('permission:transactions-access')->name('pdf.transactions.shipping');
    Route::get('/documents/receivables/{receivable}/pdf', [\App\Http\Controllers\DocumentController::class, 'receivable'])->middleware('permission:receivables-access')->name('pdf.receivables.show');
    Route::get('/documents/payables/{payable}/pdf', [\App\Http\Controllers\DocumentController::class, 'payable'])->middleware('permission:payables-access')->name('pdf.payables.show');

    Route::get('/settings/payments', [PaymentSettingController::class, 'edit'])->middleware('permission:payment-settings-access')->name('settings.payments.edit');
    Route::put('/settings/payments', [PaymentSettingController::class, 'update'])->middleware(['permission:payment-settings-update', 'step_up'])->name('settings.payments.update');

    // settings target penjualan
    Route::get('/settings/target', [\App\Http\Controllers\Apps\SettingController::class, 'target'])->middleware('permission:dashboard-access')->name('settings.target');
    Route::post('/settings/target', [\App\Http\Controllers\Apps\SettingController::class, 'updateTarget'])->middleware('permission:dashboard-access')->name('settings.target.update');
    Route::get('/settings/store', [\App\Http\Controllers\Apps\SettingController::class, 'storeProfile'])->middleware('permission:dashboard-access')->name('settings.store');
    Route::post('/settings/store', [\App\Http\Controllers\Apps\SettingController::class, 'updateStoreProfile'])->middleware('permission:dashboard-access')->name('settings.store.update');
    Route::get('/settings/loyalty', [\App\Http\Controllers\Apps\SettingController::class, 'loyalty'])->middleware('permission:dashboard-access')->name('settings.loyalty');
    Route::post('/settings/loyalty', [\App\Http\Controllers\Apps\SettingController::class, 'updateLoyalty'])->middleware('permission:dashboard-access')->name('settings.loyalty.update');

    // settings bank accounts
    Route::get('/settings/bank-accounts', [\App\Http\Controllers\Apps\BankAccountController::class, 'index'])->middleware('permission:payment-settings-access')->name('settings.bank-accounts.index');
    Route::get('/settings/bank-accounts/create', [\App\Http\Controllers\Apps\BankAccountController::class, 'create'])->middleware('permission:payment-settings-update')->name('settings.bank-accounts.create');
    Route::post('/settings/bank-accounts', [\App\Http\Controllers\Apps\BankAccountController::class, 'store'])->middleware(['permission:payment-settings-update', 'step_up'])->name('settings.bank-accounts.store');
    Route::get('/settings/bank-accounts/{bankAccount}/edit', [\App\Http\Controllers\Apps\BankAccountController::class, 'edit'])->middleware('permission:payment-settings-update')->name('settings.bank-accounts.edit');
    Route::put('/settings/bank-accounts/{bankAccount}', [\App\Http\Controllers\Apps\BankAccountController::class, 'update'])->middleware(['permission:payment-settings-update', 'step_up'])->name('settings.bank-accounts.update');
    Route::delete('/settings/bank-accounts/{bankAccount}', [\App\Http\Controllers\Apps\BankAccountController::class, 'destroy'])->middleware(['permission:payment-settings-update', 'step_up'])->name('settings.bank-accounts.destroy');
    Route::patch('/settings/bank-accounts/{bankAccount}/toggle', [\App\Http\Controllers\Apps\BankAccountController::class, 'toggleActive'])->middleware(['permission:payment-settings-update', 'step_up'])->name('settings.bank-accounts.toggle');
    Route::post('/settings/bank-accounts/order', [\App\Http\Controllers\Apps\BankAccountController::class, 'updateOrder'])->middleware(['permission:payment-settings-update', 'step_up'])->name('settings.bank-accounts.order');

    // settings warehouses
    Route::resource('/settings/warehouses', WarehouseController::class)
        ->except('show')
        ->names('settings.warehouses')
        ->middlewareFor('index', 'permission:warehouses-access')
        ->middlewareFor('store', 'permission:warehouses-create')
        ->middlewareFor('update', 'permission:warehouses-update')
        ->middlewareFor('destroy', 'permission:warehouses-delete');

    // confirm payment for bank transfer
    Route::patch('/transactions/{transaction}/confirm-payment', [TransactionController::class, 'confirmPayment'])->middleware(['permission:transactions-confirm-payment', 'step_up'])->name('transactions.confirm-payment');

    // discount approval
    Route::get('/discount-approvals', [DiscountApprovalController::class, 'pending'])->middleware('permission:discounts-approve')->name('discount-approvals.pending');
    Route::post('/discount-approvals/{transaction}/approve', [DiscountApprovalController::class, 'approve'])->middleware('permission:discounts-approve')->name('discount-approvals.approve');
    Route::post('/discount-approvals/{transaction}/deny', [DiscountApprovalController::class, 'deny'])->middleware('permission:discounts-approve')->name('discount-approvals.deny');

    // reports
    Route::get('/reports/sales', [SalesReportController::class, 'index'])->middleware('permission:reports-access')->name('reports.sales.index');
    Route::get('/reports/profits', [ProfitReportController::class, 'index'])->middleware('permission:profits-access')->name('reports.profits.index');
    Route::get('/reports/insights', [AdvancedSalesInsightsController::class, 'index'])->middleware('permission:reports-access')->name('reports.insights.index');

    // aging & reminders
    Route::get('/aging', [\App\Http\Controllers\Apps\AgingController::class, 'index'])->middleware('permission:receivables-access')->name('aging.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
