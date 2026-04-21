@php
  $es  = ($language ?? 'en') === 'es';
  $order   = $orderData;
  $receipt = $receiptRecord;

  $brandMap = [
    'VISA' => 'Visa', 'MASTERCARD' => 'Mastercard', 'AMEX' => 'Amex',
    'DINERS' => 'Diners', 'DISCOVER' => 'Discover',
    'CARD' => ($es ? 'Tarjeta' : 'Card'), 'WALLET' => 'Wallet',
    'BANK_TRANSFER' => ($es ? 'Transferencia' : 'Bank Transfer'),
  ];
  $brandLabel = $brandMap[strtoupper($receipt->payment_card_brand ?? '')] ?? ($receipt->payment_card_brand ?? ($es ? 'Tarjeta' : 'Card'));
@endphp
<!DOCTYPE html>
<html lang="{{ $es ? 'es' : 'en' }}">
<head>
  <meta charset="UTF-8"/>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #1e293b; background: #fff; padding: 32px 36px; }

    /* ── Header ── */
    .header { border-top: 5px solid #ea5500; padding-top: 20px; text-align: center; margin-bottom: 20px; }
    .header img { width: 200px; display: block; margin: 0 auto 10px; }
    .header .subtitle { font-size: 10px; color: #64748b; letter-spacing: 0.08em; text-transform: uppercase; font-weight: bold; }

    /* ── Divider ── */
    .divider { height: 1px; background: #f1f5f9; margin: 16px 0; }

    /* ── Meta bar ── */
    .meta-bar { display: table; width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 18px; }
    .meta-cell { display: table-cell; padding: 10px 14px; border-right: 1px solid #e2e8f0; vertical-align: top; }
    .meta-cell:last-child { border-right: none; }
    .meta-label { font-size: 9px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; font-weight: bold; margin-bottom: 3px; }
    .meta-value { font-size: 11px; font-weight: bold; color: #1e293b; font-family: monospace; }

    /* ── Section heading ── */
    .section-title { font-size: 9px; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 10px; }

    /* ── Products table ── */
    .products-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .products-table th { font-size: 9px; color: #94a3b8; text-transform: uppercase; font-weight: bold; letter-spacing: 0.05em; padding: 6px 8px; border-bottom: 2px solid #e2e8f0; text-align: left; }
    .products-table td { padding: 9px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: 11px; }
    .product-name { font-weight: bold; color: #1e293b; }
    .product-comment { font-style: italic; color: #64748b; font-size: 10px; margin-top: 2px; }
    .text-right { text-align: right; }
    .text-bold { font-weight: bold; }

    /* ── Price breakdown ── */
    .breakdown { width: 100%; border-collapse: collapse; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 18px; }
    .breakdown td { padding: 8px 14px; font-size: 11px; border-bottom: 1px solid #f1f5f9; }
    .breakdown .total-row td { border-top: 2px solid #e2e8f0; border-bottom: none; font-size: 13px; font-weight: 800; }
    .breakdown .total-row .total-amount { color: #ea5500; }

    /* ── Info boxes ── */
    .info-row { display: table; width: 100%; margin-bottom: 18px; border-spacing: 8px; }
    .info-box { display: table-cell; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; vertical-align: top; width: 50%; }
    .info-box-label { font-size: 9px; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 5px; }
    .info-box-value { font-size: 11px; color: #1e293b; line-height: 1.5; }
    .info-box-value strong { font-weight: bold; }

    /* ── Final sale acknowledgment ── */
    .ack-box { background: #fffbeb; border: 1px solid #f59e0b; border-left: 4px solid #f59e0b; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; }
    .ack-box p { font-size: 9px; color: #92400e; line-height: 1.6; }
    .ack-box .ack-check { font-weight: bold; color: #78350f; }

    /* ── Legal notice ── */
    .legal { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 6px; padding: 10px 14px; margin-bottom: 18px; }
    .legal p { font-size: 9px; color: #92400e; line-height: 1.5; }
    .legal strong { font-weight: bold; }

    /* ── Footer ── */
    .footer { border-top: 1px solid #e2e8f0; padding-top: 14px; text-align: center; }
    .footer p { font-size: 9px; color: #94a3b8; line-height: 1.6; }
    .footer a { color: #ea5500; text-decoration: none; }
    .footer .legal-note { font-size: 8px; color: #cbd5e1; margin-top: 6px; }
  </style>
</head>
<body>

  {{-- ── Header ── --}}
  <div class="header">
    <img src="{{ public_path('images/LOGO12-2048x587.png') }}" alt="Toggo"/>
    <p class="subtitle">{{ $es ? 'Recibo oficial de compra' : 'Official Purchase Receipt' }}</p>
  </div>
  <div class="divider"></div>

  {{-- ── Meta bar ── --}}
  <div class="meta-bar">
    @if($receipt->invoice_number)
    <div class="meta-cell">
      <div class="meta-label">{{ $es ? 'Factura' : 'Invoice' }}</div>
      <div class="meta-value">{{ $receipt->invoice_number }}</div>
    </div>
    @endif
    @if($order['order_token'] ?? null)
    <div class="meta-cell">
      <div class="meta-label">{{ $es ? 'Pedido #' : 'Order #' }}</div>
      <div class="meta-value">{{ $order['order_token'] }}</div>
    </div>
    @endif
    @if($receipt->payment_approved_at)
    <div class="meta-cell">
      <div class="meta-label">{{ $es ? 'Fecha de pago' : 'Payment date' }}</div>
      <div class="meta-value">{{ $receipt->payment_approved_at->format($es ? 'd/m/Y H:i' : 'm/d/Y H:i') }} UTC</div>
    </div>
    @endif
    <div class="meta-cell">
      <div class="meta-label">{{ $es ? 'Cliente' : 'Customer' }}</div>
      <div class="meta-value" style="font-family: inherit;">{{ $receipt->customer_name }}</div>
    </div>
  </div>

  {{-- ── Products ── --}}
  <p class="section-title">{{ $es ? 'Productos adquiridos' : 'Items purchased' }}</p>
  <table class="products-table">
    <thead>
      <tr>
        <th>{{ $es ? 'Producto' : 'Product' }}</th>
        <th class="text-right">{{ $es ? 'Cant.' : 'Qty' }}</th>
        <th class="text-right">{{ $es ? 'Precio unit.' : 'Unit price' }}</th>
        <th class="text-right">{{ $es ? 'Subtotal' : 'Subtotal' }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($order['purchase_order_details'] ?? [] as $item)
      @php
        $prod      = $item['product'] ?? [];
        $qty       = $item['amount']  ?? 1;
        $price     = $item['price']   ?? 0;
        $priceFmt  = $item['price_format'] ?? ('$' . number_format($price, 2));
        $comment   = $item['comment'] ?? null;
      @endphp
      <tr>
        <td>
          <div class="product-name">{{ $prod['name'] ?? '—' }}</div>
          @if($comment)
            <div class="product-comment">{{ $comment }}</div>
          @endif
        </td>
        <td class="text-right">{{ $qty }}</td>
        <td class="text-right">{{ $priceFmt }}</td>
        <td class="text-right text-bold">${{ number_format($price * $qty, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  {{-- ── Price breakdown ── --}}
  <p class="section-title">{{ $es ? 'Desglose de costos' : 'Cost breakdown' }}</p>
  <table class="breakdown">
    @foreach($taxes as $tax)
    <tr>
      <td style="color:#64748b;">{{ $tax['label'] ?? $tax['name'] }}</td>
      <td class="text-right text-bold">${{ number_format((float)($tax['amount'] ?? 0), 2) }}</td>
    </tr>
    @endforeach
    <tr class="total-row">
      <td>Total</td>
      <td class="text-right total-amount">${{ number_format((float)($receipt->payment_amount ?? 0), 2) }} {{ $receipt->payment_currency }}</td>
    </tr>
  </table>

  {{-- ── Shipping + Payment ── --}}
  <div class="info-row">
    @if($receipt->shipping_address)
    <div class="info-box">
      <div class="info-box-label">📦 {{ $es ? 'Dirección de envío' : 'Shipping address' }}</div>
      <div class="info-box-value">{{ $receipt->shipping_address }}</div>
    </div>
    @endif
    <div class="info-box">
      <div class="info-box-label">💳 {{ $es ? 'Método de pago' : 'Payment method' }}</div>
      <div class="info-box-value">
        <strong>{{ $brandLabel }}</strong>
        @if($receipt->payment_last_4) ···· {{ $receipt->payment_last_4 }}@endif<br/>
        @if($receipt->payment_transaction_id)
          <span style="font-size:9px;color:#64748b;">{{ $es ? 'ID Transacción:' : 'Transaction ID:' }} {{ $receipt->payment_transaction_id }}</span><br/>
        @endif
        @if($receipt->payment_authorization_code)
          <span style="font-size:9px;color:#64748b;">{{ $es ? 'Código aut.:' : 'Auth. code:' }} {{ $receipt->payment_authorization_code }}</span>
        @endif
      </div>
    </div>
  </div>

  {{-- ── Final sale acknowledgment ── --}}
  @php
    $ackAt = $order['final_sale_acknowledged_at'] ?? null;
    $ackTs = $ackAt ? \Carbon\Carbon::parse($ackAt)->format($es ? 'd/m/Y H:i' : 'm/d/Y H:i') . ' UTC' : null;
  @endphp
  @if($order['final_sale_acknowledged'] ?? false)
  <div class="ack-box">
    <p>
      <span class="ack-check">✔ {{ $es ? 'Reconocimiento de venta final' : 'Final Sale Acknowledgment' }}</span><br/>
      {{ $es
        ? 'El cliente confirmó expresamente que entiende que este es un artículo de venta final con precios especiales y que no se aceptan devoluciones.'
        : 'The customer expressly confirmed they understand this is a final sale item with special pricing and that no returns are accepted.'
      }}
      @if($ackTs)
        <br/><span style="color:#a16207;">{{ $es ? 'Confirmado el:' : 'Confirmed at:' }} {{ $ackTs }}</span>
      @endif
    </p>
  </div>
  @endif

  {{-- ── Legal notice ── --}}
  <div class="legal">
    <p>
      <strong>{{ $es ? 'Documento legal:' : 'Legal document:' }}</strong>
      {{ $es
        ? 'Este recibo es el comprobante oficial de su compra. Consérvelo para cualquier reclamo, devolución o disputa de cargo. Emitido por Toggolac / Toggo — Sin Límites.'
        : 'This receipt is the official proof of your purchase. Keep it for any claim, return, or chargeback dispute. Issued by Toggolac / Toggo — Sin Límites.'
      }}
    </p>
  </div>

  {{-- ── Footer ── --}}
  <div class="footer">
    <p>
      {{ $es ? 'Preguntas: ' : 'Questions: ' }}
      <a href="mailto:info@toggolac.com">info@toggolac.com</a>
      &nbsp;|&nbsp;
      <a href="https://toggolac.com">toggolac.com</a>
    </p>
    <p class="legal-note">
      © {{ date('Y') }} Toggolac / Toggo — Sin Límites.
      {{ $es ? 'Todos los derechos reservados.' : 'All rights reserved.' }}
      {{ $es
        ? 'Este documento fue generado electrónicamente el ' . now()->format('d/m/Y H:i') . ' UTC.'
        : 'This document was electronically generated on ' . now()->format('m/d/Y H:i') . ' UTC.'
      }}
    </p>
  </div>

</body>
</html>
