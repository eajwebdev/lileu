<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\HomeRedirectController;
use App\Http\Controllers\PaymentPageController;
use App\Http\Controllers\Portal;
use App\Http\Controllers\Pos\TerminalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\Site;
use App\Http\Controllers\Webhooks\PayMongoWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public storefront
|--------------------------------------------------------------------------
*/
Route::get('/', [Site\LandingController::class, 'index'])->name('home');
Route::get('/products', [Site\CatalogController::class, 'index'])->name('products.index');
Route::get('/products/{product:slug}', [Site\CatalogController::class, 'show'])->name('products.show');
Route::get('/become-a-reseller', [Site\ResellerApplicationController::class, 'create'])->name('reseller.apply');
Route::post('/become-a-reseller', [Site\ResellerApplicationController::class, 'store'])->name('reseller.apply.store');
Route::get('/become-a-reseller/received', [Site\ResellerApplicationController::class, 'received'])->name('reseller.apply.received');

/*
|--------------------------------------------------------------------------
| Payments (PayMongo QR Ph) + receipts
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/paymongo', [PayMongoWebhookController::class, 'handle'])->name('webhooks.paymongo');

Route::middleware('auth')->group(function () {
    Route::get('/pay/{payment:receipt_number}', [PaymentPageController::class, 'show'])->name('pay.show');
    Route::post('/pay/{payment:receipt_number}/simulate', [PaymentPageController::class, 'simulate'])->name('pay.simulate');
    Route::get('/pay/{payment:receipt_number}/status', [PaymentPageController::class, 'status'])->name('pay.status');
    Route::get('/pay/{payment:receipt_number}/success', [PaymentPageController::class, 'success'])->name('pay.success');

    Route::get('/receipts/{payment:receipt_number}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::get('/receipts/{payment:receipt_number}/print', [ReceiptController::class, 'print'])->name('receipts.print');
    Route::get('/receipts/{payment:receipt_number}/pdf', [ReceiptController::class, 'pdf'])->name('receipts.pdf');
    Route::get('/orders/{order:order_number}/summary', [ReceiptController::class, 'summary'])->name('receipts.summary');
    Route::get('/orders/{order:order_number}/summary/pdf', [ReceiptController::class, 'summaryPdf'])->name('receipts.summary.pdf');
});

/*
|--------------------------------------------------------------------------
| Reseller portal
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:reseller'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/status', [Portal\StatusController::class, 'show'])->name('status');

    Route::middleware('reseller.approved')->group(function () {
        Route::get('/', [Portal\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/catalog', [Portal\CatalogController::class, 'index'])->name('catalog');
        Route::get('/orders', [Portal\OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/new', [Portal\OrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [Portal\OrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order:order_number}', [Portal\OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order:order_number}/pay', [Portal\OrderController::class, 'pay'])->name('orders.pay');
        Route::get('/messages', [Portal\MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages', [Portal\MessageController::class, 'store'])->name('messages.store');
    });
});

/*
|--------------------------------------------------------------------------
| Point of sale (cashier + admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:cashier'])->prefix('pos')->name('pos.')->group(function () {
    Route::get('/', [TerminalController::class, 'index'])->name('index');
    Route::post('/sales', [TerminalController::class, 'store'])->name('sales.store');
    Route::get('/sales/{sale:sale_number}/receipt', [TerminalController::class, 'receipt'])->name('sales.receipt');
    Route::get('/sales', [TerminalController::class, 'history'])->name('sales.index');
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/products', [Admin\ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [Admin\ProductController::class, 'store'])->name('products.store');
    Route::put('/products/{product}', [Admin\ProductController::class, 'update'])->name('products.update');
    Route::patch('/products/{product}/availability', [Admin\ProductController::class, 'availability'])->name('products.availability');
    Route::delete('/products/{product}', [Admin\ProductController::class, 'destroy'])->name('products.destroy');

    Route::post('/categories', [Admin\CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/resellers', [Admin\ResellerController::class, 'index'])->name('resellers.index');
    Route::post('/resellers', [Admin\ResellerController::class, 'store'])->name('resellers.store');
    Route::get('/resellers/{reseller}', [Admin\ResellerController::class, 'show'])->name('resellers.show');
    Route::put('/resellers/{reseller}', [Admin\ResellerController::class, 'update'])->name('resellers.update');
    Route::post('/resellers/{reseller}/status', [Admin\ResellerController::class, 'updateStatus'])->name('resellers.status');
    Route::post('/resellers/{reseller}/products', [Admin\ResellerController::class, 'syncProducts'])->name('resellers.products');

    Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order:order_number}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order:order_number}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('/orders/{order:order_number}/payments', [Admin\OrderController::class, 'recordPayment'])->name('orders.payments.store');
    Route::post('/payments/{payment}/void', [Admin\OrderController::class, 'voidPayment'])->name('payments.void');

    Route::get('/payments', [Admin\PaymentController::class, 'index'])->name('payments.index');

    Route::get('/consignments', [Admin\ConsignmentController::class, 'index'])->name('consignments.index');
    Route::get('/consignments/new', [Admin\ConsignmentController::class, 'create'])->name('consignments.create');
    Route::post('/consignments', [Admin\ConsignmentController::class, 'store'])->name('consignments.store');
    Route::get('/consignments/{consignment:consignment_number}', [Admin\ConsignmentController::class, 'show'])->name('consignments.show');
    Route::get('/consignments/{consignment:consignment_number}/slip', [Admin\ConsignmentController::class, 'slip'])->name('consignments.slip');
    Route::post('/consignments/{consignment:consignment_number}/settle', [Admin\ConsignmentController::class, 'settle'])->name('consignments.settle');
    Route::post('/consignments/{consignment:consignment_number}/cancel', [Admin\ConsignmentController::class, 'cancel'])->name('consignments.cancel');
    Route::get('/consignment-settlements/{settlement}/receipt', [Admin\ConsignmentController::class, 'settlementReceipt'])->name('consignments.settlement.receipt');

    Route::get('/messages', [Admin\MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages/{reseller}', [Admin\MessageController::class, 'store'])->name('messages.store');

    Route::get('/ledger', [Admin\LedgerController::class, 'index'])->name('ledger.index');
    Route::post('/expenses', [Admin\LedgerController::class, 'storeExpense'])->name('expenses.store');
    Route::delete('/expenses/{expense}', [Admin\LedgerController::class, 'destroyExpense'])->name('expenses.destroy');
    Route::post('/purchases', [Admin\LedgerController::class, 'storePurchase'])->name('purchases.store');
    Route::delete('/purchases/{purchase}', [Admin\LedgerController::class, 'destroyPurchase'])->name('purchases.destroy');

    Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');

    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');

    Route::get('/settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');
});

/*
|--------------------------------------------------------------------------
| Account
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', HomeRedirectController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
