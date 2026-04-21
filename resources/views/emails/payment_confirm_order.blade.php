@php
  $es = $language === 'es';

  // Friendly card brand label
  $brandMap = [
      'VISA'       => 'Visa',
      'MASTERCARD' => 'Mastercard',
      'AMEX'       => 'Amex',
      'DINERS'     => 'Diners',
      'DISCOVER'   => 'Discover',
      'ELO'        => 'Elo',
      'HIPERCARD'  => 'Hipercard',
      'CARD'       => $es ? 'Tarjeta' : 'Card',
      'WALLET'     => 'Wallet',
      'BANK_TRANSFER' => $es ? 'Transferencia' : 'Bank Transfer',
  ];
  $brandLabel = $brandMap[strtoupper($paymentBrand ?? '')] ?? ($paymentBrand ?? ($es ? 'Tarjeta' : 'Card'));
@endphp
<!DOCTYPE html>
<html lang="{{ $es ? 'es' : 'en' }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>{{ $es ? 'Recibo de pedido — Toggolac' : 'Order Receipt — Toggolac' }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Lato,Tahoma,Arial,sans-serif;color:#1e293b;">

  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0"
               style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

          {{-- ── Header: white, logo centred, orange accent top bar ── --}}
          <tr>
            <td style="background:#ffffff;border-top:5px solid #ea5500;padding:28px 32px 20px;text-align:center;">
              <img
                src="{{ env('APP_URL', 'https://toggolac.com') }}/images/LOGO12-2048x587.png"
                alt="Toggo"
                width="240"
                style="display:block;margin:0 auto 14px;max-width:100%;height:auto;" />
              <p style="margin:0;color:#64748b;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;font-weight:600;">
                {{ $es ? 'Confirmación y recibo de pedido' : 'Order Confirmation & Receipt' }}
              </p>
            </td>
          </tr>

          {{-- ── Divider ── --}}
          <tr><td style="height:1px;background:#f1f5f9;"></td></tr>

          {{-- ── Greeting ── --}}
          <tr>
            <td style="padding:24px 32px 0;">
              <p style="margin:0 0 6px;font-size:20px;font-weight:700;color:#1e293b;">
                {{ $es ? "¡Hola {$name}, gracias por tu pedido! 🎉" : "Hi {$name}, thank you for your order! 🎉" }}
              </p>
              <p style="margin:0;font-size:14px;color:#64748b;line-height:1.6;">
                {{ $es
                    ? 'Hemos recibido tu compra y nuestro equipo ya está trabajando en ella. Guarda este correo como tu recibo.'
                    : 'We\'ve received your purchase and our team is already working on it. Keep this email as your receipt.' }}
              </p>
            </td>
          </tr>

          {{-- ── Order meta bar: Invoice · Order # · Date · Name ── --}}
          <tr>
            <td style="padding:20px 32px;">
              <table width="100%" cellpadding="0" cellspacing="0"
                     style="background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;overflow:hidden;">
                <tr>
                  @if($invoiceNumber)
                  <td style="padding:12px 16px;border-right:1px solid #e2e8f0;">
                    <p style="margin:0 0 3px;font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">
                      {{ $es ? 'Factura' : 'Invoice' }}
                    </p>
                    <p style="margin:0;font-size:12px;font-weight:700;color:#1e293b;font-family:monospace;">{{ $invoiceNumber }}</p>
                  </td>
                  @endif
                  @if($orderToken)
                  <td style="padding:12px 16px;border-right:1px solid #e2e8f0;">
                    <p style="margin:0 0 3px;font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">
                      {{ $es ? 'Pedido #' : 'Order #' }}
                    </p>
                    <p style="margin:0;font-size:12px;font-weight:700;color:#1e293b;font-family:monospace;">{{ $orderToken }}</p>
                  </td>
                  @endif
                  @if($orderDate)
                  <td style="padding:12px 16px;border-right:1px solid #e2e8f0;">
                    <p style="margin:0 0 3px;font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">
                      {{ $es ? 'Fecha' : 'Date' }}
                    </p>
                    <p style="margin:0;font-size:12px;font-weight:700;color:#1e293b;">{{ $orderDate }}</p>
                  </td>
                  @endif
                  <td style="padding:12px 16px;">
                    <p style="margin:0 0 3px;font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">
                      {{ $es ? 'Cliente' : 'Customer' }}
                    </p>
                    <p style="margin:0;font-size:12px;font-weight:700;color:#1e293b;">{{ $name }}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          {{-- ── Products ── --}}
          <tr>
            <td style="padding:0 32px;">
              <p style="margin:0 0 14px;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;">
                {{ $es ? 'Productos pedidos' : 'Items ordered' }}
              </p>
              <table width="100%" cellpadding="0" cellspacing="0">
                @foreach($products as $item)
                @php
                  $product  = $item['product']       ?? [];
                  $imgUrl   = $product['image']       ?? ($product['images'][0] ?? null);
                  $name_p   = $product['name']        ?? '—';
                  $qty      = $item['amount']         ?? 1;
                  $price    = $item['price']          ?? 0;
                  $prFmt    = $item['price_format']   ?? ('$' . number_format($price, 2));
                  $comment  = $item['comment']        ?? null;
                  $lineTotal = '$' . number_format($price * $qty, 2);
                @endphp
                <tr>
                  <td style="padding:12px 0;border-bottom:1px solid #f1f5f9;vertical-align:top;width:80px;">
                    @if($imgUrl)
                      <img src="{{ $imgUrl }}" alt="{{ $name_p }}" width="68" height="68"
                           style="border-radius:10px;object-fit:cover;display:block;border:1px solid #e2e8f0;background:#f8fafc;" />
                    @else
                      <div style="width:68px;height:68px;border-radius:10px;background:#f1f5f9;border:1px solid #e2e8f0;text-align:center;line-height:68px;font-size:26px;">📦</div>
                    @endif
                  </td>
                  <td style="padding:12px 0 12px 14px;border-bottom:1px solid #f1f5f9;vertical-align:top;">
                    <p style="margin:0 0 4px;font-size:14px;font-weight:700;color:#1e293b;">{{ $name_p }}</p>
                    @if($comment)
                      <p style="margin:0 0 4px;font-size:12px;color:#64748b;font-style:italic;">{{ $comment }}</p>
                    @endif
                    <p style="margin:0;font-size:12px;color:#64748b;">
                      {{ $es ? 'Cant' : 'Qty' }}: <strong>{{ $qty }}</strong>
                      &nbsp;·&nbsp;
                      {{ $es ? 'Unitario' : 'Unit' }}: <strong>{{ $prFmt }}</strong>
                    </p>
                  </td>
                  <td style="padding:12px 0;border-bottom:1px solid #f1f5f9;vertical-align:top;text-align:right;white-space:nowrap;">
                    <span style="font-size:14px;font-weight:700;color:#1e293b;">{{ $lineTotal }}</span>
                  </td>
                </tr>
                @endforeach
              </table>
            </td>
          </tr>

          {{-- ── Price breakdown ── --}}
          <tr>
            <td style="padding:20px 32px;">
              <table width="100%" cellpadding="0" cellspacing="0"
                     style="background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;overflow:hidden;">
                @foreach($taxes as $tax)
                <tr>
                  <td style="padding:9px 18px;font-size:13px;color:#64748b;border-bottom:1px solid #f1f5f9;">
                    {{ $tax['label'] ?? $tax['name'] }}
                  </td>
                  <td style="padding:9px 18px;font-size:13px;color:#1e293b;font-weight:600;text-align:right;border-bottom:1px solid #f1f5f9;">
                    ${{ number_format((float)($tax['amount'] ?? 0), 2) }}
                  </td>
                </tr>
                @endforeach
                <tr>
                  <td style="padding:14px 18px;border-top:2px solid #e2e8f0;font-size:16px;font-weight:800;color:#1e293b;">
                    Total
                  </td>
                  <td style="padding:14px 18px;border-top:2px solid #e2e8f0;font-size:17px;font-weight:800;color:#ea5500;text-align:right;">
                    {{ is_numeric($total) ? '$' . number_format((float)$total, 2) : $total }}
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          {{-- ── Shipping address + Payment method (2-col) ── --}}
          <tr>
            <td style="padding:0 32px 24px;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>

                  {{-- Shipping address --}}
                  @if($destinationAddress)
                  <td style="vertical-align:top;padding-right:8px;">
                    <table width="100%" cellpadding="0" cellspacing="0"
                           style="background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;padding:14px 16px;height:100%;">
                      <tr><td>
                        <p style="margin:0 0 5px;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;">
                          📦 {{ $es ? 'Dirección de envío' : 'Shipping address' }}
                        </p>
                        <p style="margin:0;font-size:13px;color:#1e293b;line-height:1.6;">{{ $destinationAddress }}</p>
                      </td></tr>
                    </table>
                  </td>
                  @endif

                  {{-- Payment method --}}
                  @if($paymentLast4 || $paymentBrand)
                  <td style="vertical-align:top;padding-left:8px;width:44%;">
                    <table width="100%" cellpadding="0" cellspacing="0"
                           style="background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;padding:14px 16px;height:100%;">
                      <tr><td>
                        <p style="margin:0 0 5px;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;">
                          💳 {{ $es ? 'Método de pago' : 'Payment method' }}
                        </p>
                        <p style="margin:0;font-size:13px;color:#1e293b;font-weight:600;">
                          {{ $brandLabel }}
                          @if($paymentLast4)
                            &nbsp;····&nbsp;{{ $paymentLast4 }}
                          @endif
                        </p>
                      </td></tr>
                    </table>
                  </td>
                  @endif

                </tr>
              </table>
            </td>
          </tr>

          {{-- ── CTA button ── --}}
          <tr>
            <td style="padding:0 32px 32px;text-align:center;">
              <a href="{{ env('FRONT_URL', 'https://toggolac.com') }}/account/orders"
                 style="display:inline-block;background:linear-gradient(135deg,#ea5500,#ff8a00);color:#ffffff;font-size:14px;font-weight:700;padding:14px 36px;border-radius:999px;text-decoration:none;letter-spacing:0.02em;">
                {{ $es ? 'Ver mis pedidos →' : 'View my orders →' }}
              </a>
            </td>
          </tr>

          {{-- ── Footer ── --}}
          <tr>
            <td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:22px 32px;text-align:center;">
              <p style="margin:0 0 5px;font-size:12px;color:#94a3b8;line-height:1.6;">
                {{ $es ? '¿Preguntas? Escríbenos a' : 'Questions? Write to us at' }}
                <a href="mailto:info@toggolac.com" style="color:#ea5500;text-decoration:none;font-weight:600;">info@toggolac.com</a>
              </p>
              <p style="margin:0;font-size:11px;color:#cbd5e1;">
                © {{ date('Y') }} Toggolac. {{ $es ? 'Todos los derechos reservados.' : 'All rights reserved.' }}
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>
