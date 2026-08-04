<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Log Descriptions
    |--------------------------------------------------------------------------
    */
    'products' => [
        'created' => 'New product created',
        'updated' => 'Product data updated',
        'deleted' => 'Product deleted',
    ],
    'categories' => [
        'created' => 'New category created',
        'updated' => 'Category updated',
        'deleted' => 'Category deleted',
    ],
    'customers' => [
        'created' => 'New customer created',
        'updated' => 'Customer data updated',
        'deleted' => 'Customer deleted',
    ],
    'suppliers' => [
        'created' => 'New supplier created',
        'updated' => 'Supplier data updated',
        'deleted' => 'Supplier deleted',
    ],
    'transactions' => [
        'created' => 'New transaction created',
        'updated' => 'Transaction updated',
        'voided' => 'Transaction voided',
        'payment_confirmed' => 'Transaction payment confirmed',
    ],
    'cashier_shifts' => [
        'opened' => 'Cashier shift opened',
        'closed' => 'Cashier shift closed',
        'force_closed' => 'Cashier shift force closed',
    ],
    'stock' => [
        'adjusted' => 'Stock adjusted',
        'transferred' => 'Stock transferred',
        'opname_finalized' => 'Stock opname finalized',
    ],
    'purchase_orders' => [
        'created' => 'Purchase Order created',
        'placed' => 'Purchase Order placed',
        'cancelled' => 'Purchase Order cancelled',
    ],
    'users' => [
        'created' => 'New user created',
        'updated' => 'User data updated',
        'deleted' => 'User deleted',
        'role_changed' => 'User role changed',
    ],
    'roles' => [
        'created' => 'New role created',
        'updated' => 'Role updated',
        'deleted' => 'Role deleted',
        'permission_changed' => 'Role permission changed',
    ],
    'settings' => [
        'updated' => 'Settings updated',
    ],
    'bank_accounts' => [
        'created' => 'Bank account added',
        'updated' => 'Bank account updated',
        'deleted' => 'Bank account deleted',
    ],
    'payment_settings' => [
        'updated' => 'Payment settings updated',
    ],
];
