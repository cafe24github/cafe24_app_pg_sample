<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SynchronousCheckout\InternalController as SyncCheckoutController;
use App\Http\Controllers\AsynchronousCheckout\InternalController as AsyncCheckout;
use App\Http\Controllers\ExternalCheckout\InternalController as ExternalCheckout;
use App\Http\Controllers\AsynchronousRefund\InternalController as AsyncRefund;
use App\Http\Controllers\SynchronousRefund\InternalController as SyncRefund;

/**
 * Checkout (Synchronous) Endpoints
 */
Route::group(['prefix' => 'synchronous-checkout'], function () {
    // This endpoint is called by Cafe24, receiving the checkout data from the Cafe24 EC mall.
    // It will return the payment URL where Cafe24 will redirect the buyer to approve the payment.
    Route::post('/checkout', [SyncCheckoutController::class, 'createCheckout']);

    // The PG Company will call this endpoint after the buyer approves the payment request.
    // It will verify and update the order status in the app's database.
    // It will then redirect to the Cafe24's return URL (return_url) to update Cafe24 the payment status.
    Route::post('/checkout/callback', [SyncCheckoutController::class, 'handleCallback']);

    // Cafe24 will call this endpoint to verify the payment status of the order from the PG Company which is already stored in the PG app's database.
    Route::post('/checkout/status', [SyncCheckoutController::class, 'getPaymentStatus']);
});

/**
 * Checkout (Asynchronous) Endpoints
 */
Route::group(['prefix' => 'asynchronous-checkout'], function () {
    // This endpoint is called by Cafe24, receiving the checkout data from the Cafe24 EC mall.
    // It will return the payment URL where Cafe24 will redirect the buyer to approve the payment.
    Route::post('/checkout', [AsyncCheckout::class, 'createCheckout']);

    // The PG Company will call this endpoint after the buyer approves the payment request.
    // It will verify and store new order details from the PG Company.
    // It will then redirect to Cafe24's return URL (return_url).
    Route::post('/checkout/callback', [AsyncCheckout::class, 'handleCallback']);

    // The PG Company will call this endpoint to update the PG app for the payment status.
    // The app will then call the noty URL (return_noty_url) of Cafe24 to update them of the status asynchronously.
    Route::post('/checkout/webhook', [AsyncCheckout::class, 'handleWebhook']);
});

/**
 * External Checkout Endpoints
 */
Route::group(['prefix' => 'external-checkout'], function () {
    /**
     * Payment Button Initialization
     */
    // Cafe24 Mall Front will call this endpoint if the PG app's "script-caller.js" is installed in a shop through the Cafe24 Scripttag API.
    // This endpoint will append the functional JavaScript files for the payment button.
    Route::get('/script', [ExternalCheckout::class, 'getScript'])->name('script-caller');

    // Cafe24 Mall Front will call this endpoint to retrieve the necessary data to initialize and display the payment button in the shop.
    Route::get('/script/data', [ExternalCheckout::class, 'getScriptData']);

    /**
     * External Checkout Request
     */
    // The Cafe24 Mall Front will call this endpoint to request for an HMAC data.
    // The generated HMAC is going to be used in reserving an order to the Cafe24 (precreateOrder API)
    Route::get('/script/hmac', [ExternalCheckout::class, 'createOrderRequestHmac']);

    // The Cafe24 Mall Front will call this endpoint to create a checkout request to the PG Company and process the response which will be passed to the PG Company's JavaScript SDK to start the payment process.
    Route::post('/payload', [ExternalCheckout::class, 'createPayload']);

    // The PG Company will call this endpoint to preview the summary of order that the buyer will pay.
    // This endpoint is optional because there are PG Companies that will redirect the buyer into their own payment preview page, but there are also PG Companies that will require the PG app to provide this endpoint.
    Route::get('/order/review', [ExternalCheckout::class, 'displayOrderPreview']);

    // The PG app will call this endpoint to send the approval of payment to the PG Company when the buyer proceeds with the checkout after confirming in the Order Preview Page.
    // This endpoint is also only required if the PG Company will need the PG app to provide a review page.
    Route::post('/order/pay', [ExternalCheckout::class, 'handleExternalCheckoutPayment']);

    /**
     * External Checkout Callback
     */
    // The PG Company will call this endpoint to provide updates with the payment.
    // The PG app will then send the updates to the through the external return notification URL (return_notification_url).
    Route::get('/order/callback', [ExternalCheckout::class, 'handleExternalCheckoutCallback']);
});

/**
 * Refund (Synchronous) Endpoints
 */
Route::group(['prefix' => 'synchronous-refund'], function () {
    // Cafe24 will call this endpoint for the cancellation of an order payment.
    // The PG app makes the request to the PG Company and returns the result back to Cafe24.
    Route::post('/cancel', [SyncRefund::class, 'cancelPayment']);
});

/**
 * Refund (Asynchronous) Endpoints
 */
Route::group(['prefix' => 'asynchronous-refund'], function () {
    // Cafe24 will call this endpoint for the cancellation/refund of an order payment.
    // The PG app makes the request to the PG Company.
    Route::post('/cancel', [AsyncRefund::class, 'cancelPayment']);

    // The PG Company will call this endpoint to notify the PG app of the cancellation/refund request status.
    // The app will update Cafe24 of the cancellation status by calling the cancellation noty URL (cancel_noty_url) asynchronously.
    Route::post('/cancel/webhook', [AsyncRefund::class, 'handleCancellationWebhook']);
});
