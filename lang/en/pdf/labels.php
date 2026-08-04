<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PDF Labels - Common
    |--------------------------------------------------------------------------
    */
    'common' => [
        'no' => 'No.',
        'date' => 'Date',
        'time' => 'Time',
        'status' => 'Status',
        'action' => 'Action',
        'total' => 'Total',
        'subtotal' => 'Subtotal',
        'tax' => 'Tax',
        'discount' => 'Discount',
        'payment' => 'Payment',
        'paid' => 'Paid',
        'unpaid' => 'Unpaid',
        'partial' => 'Partial',
        'due_date' => 'Due Date',
        'customer' => 'Customer',
        'cashier' => 'Cashier',
        'cash' => 'Cash',
        'change' => 'Change',
        'note' => 'Note',
        'thank_you' => 'Thank You!',
        'thank_you_message' => 'Thank you for your trust.',
        'phone' => 'Phone',
        'email' => 'Email',
        'address' => 'Address',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Labels - Invoice
    |--------------------------------------------------------------------------
    */
    'invoice' => [
        'title' => 'INVOICE',
        'invoice_number' => 'Invoice No.',
        'invoice_date' => 'Invoice Date',
        'due_date' => 'Due Date',
        'payment_status' => 'Payment Status',
        'payment_method' => 'Payment Method',
        'product_name' => 'Product Name',
        'quantity' => 'Qty',
        'unit_price' => 'Unit Price',
        'subtotal_col' => 'Subtotal',
        'grand_total' => 'TOTAL',
        'tax_amount' => 'Tax Amount',
        'shipping_cost' => 'Shipping Cost',
        'promo_discount' => 'Promo Discount',
        'manual_discount' => 'Manual Discount',
        'voucher_discount' => 'Voucher',
        'redeem_points' => 'Redeem Points',
        'loyalty_discount' => 'Loyalty Discount',
        'balance_due' => 'Balance Due',
        'amount_paid' => 'Amount Paid',
        'customer_npwp' => 'Customer NPWP',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Labels - Receipt
    |--------------------------------------------------------------------------
    */
    'receipt' => [
        'title' => 'SALES RECEIPT',
        'transaction_no' => 'Transaction No.',
        'transaction_date' => 'Date',
        'shift' => 'Shift',
        'warehouse' => 'Warehouse',
        'customer_type' => 'General',
        'member' => 'Member',
        'barcode' => 'Barcode',
        'product' => 'Product',
        'unit' => 'Unit',
        'price' => 'Price',
        'qty' => 'Qty',
        'subtotal_col' => 'Subtotal',
        'promo' => 'Promo',
        'items_total' => 'Items Total',
        'total_discount' => 'Total Discount',
        'before_tax' => 'Before Tax',
        'tax_rate' => 'TAX :rate%',
        'grand_total' => 'TOTAL',
        'payment_method' => 'Payment via',
        'cash_payment' => 'Cash',
        'card_payment' => 'Card',
        'amount_tendered' => 'Tendered',
        'change_col' => 'Change',
        'points_earned' => 'Points Earned',
        'points_redeemed' => 'Points Redeemed',
        'voucher_used' => 'Voucher',
        'footer' => 'Purchased items cannot be returned.',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Labels - Shipping Label
    |--------------------------------------------------------------------------
    */
    'shipping' => [
        'title' => 'SHIPPING LABEL',
        'ship_from' => 'Ship From',
        'ship_to' => 'Ship To',
        'weight' => 'Weight',
        'dimensions' => 'Dimensions',
        'shipping_method' => 'Shipping Method',
        'tracking_number' => 'Tracking Number',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Labels - Receivable
    |--------------------------------------------------------------------------
    */
    'receivable' => [
        'title' => 'ACCOUNTS RECEIVABLE INVOICE',
        'original_amount' => 'Original Amount',
        'amount_paid' => 'Amount Paid',
        'remaining_balance' => 'Remaining Balance',
        'overdue_days' => 'Days Overdue',
        'payment_history' => 'Payment History',
        'payment_date' => 'Payment Date',
        'payment_amount' => 'Payment Amount',
        'collector_notes' => 'Collection Notes',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Labels - Payable
    |--------------------------------------------------------------------------
    */
    'payable' => [
        'title' => 'ACCOUNTS PAYABLE INVOICE',
        'supplier' => 'Supplier',
        'original_amount' => 'Original Amount',
        'amount_paid' => 'Amount Paid',
        'remaining_balance' => 'Remaining Balance',
        'overdue_days' => 'Days Overdue',
        'payment_history' => 'Payment History',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Labels - Status
    |--------------------------------------------------------------------------
    */
    'status' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'paid' => 'Paid',
        'partial' => 'Partial',
        'overdue' => 'Overdue',
    ],
];
