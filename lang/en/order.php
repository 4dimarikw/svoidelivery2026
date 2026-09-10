<?php

return [
    'title' => 'Checkout',
    'summary' => 'Your order',
    'delivery_type' => 'Delivery method',
    'payment_method' => 'Payment method',
    'address' => 'Delivery address',
    'address_add' => 'Add address',
    'address_none' => 'You have no saved addresses — add one to get delivery.',
    'messenger' => 'Messenger for contact',
    'messenger_help' => 'A messenger link where we can reach you about this order.',
    'messenger_source' => 'Use a link from your profile',
    'messenger_other' => 'Other',
    'comment' => 'Order comment',
    'submit' => 'Place order',
    'submitting' => 'Placing order…',

    'recipient' => [
        'title' => 'Recipient details',
        'name' => 'Recipient',
        'phone' => 'Phone',
        'city' => 'City',
        'address' => 'Address',
        'not_set' => 'not set',
    ],

    'statuses' => [
        'new' => 'New',
        'pending' => 'Pending',
        'paid' => 'Paid',
        'sent' => 'Sent',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'errors' => [
        'product_unavailable' => 'Product “:title” is no longer available to order. Remove it from your cart.',
        'product_has_orders' => 'This product is used in orders. Archive it instead of deleting it.',
        'item_moved' => 'This item has already moved to another order. Refresh the page.',
        'invalid_price' => 'Enter an amount from :min to 9,999,999,999.99, with at most two decimal places using a dot or comma.',
        'amount_overflow' => 'The line amount or order total exceeds 9,999,999,999.99.',
        'item_transfer_forbidden' => 'Moving an existing item to another order through this form is not allowed.',
        'checkout_expired' => 'This checkout form has expired. Reload your cart and try again.',
        'min_amount' => 'Order amount is below the minimum for delivery with an address.',
        'min_amount_1' => 'The cart is empty or the order amount is too low.',
        'out_of_stock' => 'Product ":title" is out of stock.',
        'pack_multiplicity' => 'The quantity of Beer, Mead, Cider and Non-alcoholic items (:quantity pcs.) must be a multiple of 12 or 20.',
    ],

    'history' => [
        'title' => 'My orders',
        'empty' => 'You have no orders yet.',
        'number' => 'Order number',
        'date' => 'Date',
        'items_count' => 'Items',
    ],
];
