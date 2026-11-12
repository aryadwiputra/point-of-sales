<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;

class QuotaService
{
    public function getTenant(): ?Tenant
    {
        return TenantContext::get();
    }

    public function getPlan(): ?Plan
    {
        return $this->getTenant()?->plan;
    }

    public function canCreateOutlet(int $count = 1): bool
    {
        $plan = $this->getPlan();
        if (! $plan || TenantContext::isBypassed()) {
            return true;
        }

        return (Outlet::count() + $count) <= $plan->max_outlets;
    }

    public function canAddUser(int $count = 1): bool
    {
        $plan = $this->getPlan();
        if (! $plan || TenantContext::isBypassed()) {
            return true;
        }

        return (User::count() + $count) <= $plan->max_users;
    }

    public function canCreateProduct(int $count = 1): bool
    {
        $plan = $this->getPlan();
        if (! $plan || TenantContext::isBypassed()) {
            return true;
        }

        return (Product::count() + $count) <= $plan->max_products;
    }

    public function canAddCustomer(int $count = 1): bool
    {
        $plan = $this->getPlan();
        if (! $plan || TenantContext::isBypassed()) {
            return true;
        }

        return (Customer::count() + $count) <= $plan->max_customers;
    }

    public function hasFeature(string $feature): bool
    {
        $plan = $this->getPlan();
        if (! $plan || TenantContext::isBypassed()) {
            return true;
        }

        return $plan->hasFeature($feature);
    }

    public function getUsageSummary(): array
    {
        $plan = $this->getPlan();

        $outletsCount = Outlet::count();
        $usersCount = User::count();
        $productsCount = Product::count();
        $customersCount = Customer::count();

        return [
            'plan' => $plan ? [
                'name' => $plan->name,
                'slug' => $plan->slug,
                'features' => $plan->features ?? [],
            ] : null,
            'outlets' => [
                'current' => $outletsCount,
                'max' => $plan?->max_outlets ?? null,
                'can_create' => $this->canCreateOutlet(),
            ],
            'users' => [
                'current' => $usersCount,
                'max' => $plan?->max_users ?? null,
                'can_create' => $this->canAddUser(),
            ],
            'products' => [
                'current' => $productsCount,
                'max' => $plan?->max_products ?? null,
                'can_create' => $this->canCreateProduct(),
            ],
            'customers' => [
                'current' => $customersCount,
                'max' => $plan?->max_customers ?? null,
                'can_create' => $this->canAddCustomer(),
            ],
        ];
    }
}
