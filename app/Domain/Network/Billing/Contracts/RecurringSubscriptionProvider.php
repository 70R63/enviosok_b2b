<?php
namespace App\Domain\Network\Billing\Contracts;
interface RecurringSubscriptionProvider
{
 public function createPlan(array $payload): array;
 public function createSubscription(array $payload): array;
 public function retrieve(string $id): array;
 public function update(string $id,array $payload): array;
 public function retrievePayment(string $id): array;
 public function validateWebhook(\Illuminate\Http\Request $request,string $id): bool;
}
