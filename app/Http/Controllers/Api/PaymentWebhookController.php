<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Payments\MidtransWebhookDto;
use App\DTOs\Payments\XenditWebhookDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentWebhook\MidtransWebhookRequest;
use App\Http\Requests\PaymentWebhook\XenditWebhookRequest;
use App\Services\Payments\Webhooks\HandleMidtransWebhookService;
use App\Services\Payments\Webhooks\HandleXenditWebhookService;
use Illuminate\Http\JsonResponse;

class PaymentWebhookController extends Controller
{
    public function midtrans(
        MidtransWebhookRequest $request,
        HandleMidtransWebhookService $service
    ): JsonResponse {
        $result = $service->execute(MidtransWebhookDto::fromRequest($request));

        return response()->json($result->toArray(), $result->httpStatus);
    }

    public function xendit(
        XenditWebhookRequest $request,
        HandleXenditWebhookService $service
    ): JsonResponse {
        $result = $service->execute(XenditWebhookDto::fromRequest($request));

        return response()->json($result->toArray(), $result->httpStatus);
    }
}
