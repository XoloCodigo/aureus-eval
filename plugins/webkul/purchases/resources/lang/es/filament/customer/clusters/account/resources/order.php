<?php

return [
    'table' => [
        'columns' => [
            'reference'         => 'Referencia',
            'total-amount'      => 'Importe total',
            'confirmation-date' => 'Fecha de confirmación',
            'status'            => 'Estado',
        ],
    ],

    'products' => [
        'columns' => [
            'product'    => 'Producto',
            'quantity'   => 'Cantidad',
            'unit-price' => 'Precio unitario',
            'taxes'      => 'Impuestos',
            'discount'   => 'Descuento %',
            'amount'     => 'Importe',
        ],
    ],

    'infolist' => [
        'settings' => [
            'entries' => [
                'buyer' => 'Comprador',
            ],

            'actions' => [
                'accept' => [
                    'label' => 'Aceptar',

                    'notification' => [
                        'title' => 'Cotización aceptada',
                        'body'  => 'La solicitud de cotización se ha confirmado correctamente.',
                    ],

                    'message' => [
                        'body' => 'La solicitud de cotización ha sido confirmada por el proveedor.',
                    ],
                ],

                'decline' => [
                    'label' => 'Rechazar',

                    'notification' => [
                        'title' => 'Cotización rechazada',
                        'body'  => 'La solicitud de cotización se ha rechazado correctamente.',
                    ],

                    'message' => [
                        'body' => 'La solicitud de cotización ha sido rechazada por el proveedor.',
                    ],
                ],

                'print' => [
                    'label' => 'Descargar/Imprimir',
                ],
            ],
        ],

        'general' => [
            'entries' => [
                'purchase-order'        => 'Pedido de compra n.º :id',
                'quotation'             => 'Solicitud de cotización n.º :id',
                'order-date'            => 'Fecha del pedido',
                'from'                  => 'De',
                'confirmation-date'     => 'Fecha de confirmación',
                'receipt-date'          => 'Fecha de recepción',
                'products'              => 'Productos',
                'untaxed-amount'        => 'Importe sin impuestos',
                'tax-amount'            => 'Importe de impuestos',
                'total'                 => 'Total',
                'communication-history' => 'Historial de comunicaciones',
            ],
        ],
    ],
];
