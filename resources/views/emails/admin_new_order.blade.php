<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Nueva compra — Toggolac Admin</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Lato,Tahoma,Arial,sans-serif;color:#1e293b;">

  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0"
               style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

          {{-- Header --}}
          <tr>
            <td style="background:#1e293b;border-top:5px solid #ea5500;padding:28px 32px 20px;text-align:center;">
              <img
                src="{{ env('APP_URL', 'https://toggolac.com') }}/images/LOGO12-2048x587.png"
                alt="Toggo"
                width="200"
                style="display:block;margin:0 auto 14px;max-width:100%;height:auto;filter:brightness(0) invert(1);" />
              <p style="margin:0;color:#94a3b8;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;font-weight:600;">
                Panel de Administración
              </p>
            </td>
          </tr>

          {{-- Alert Banner --}}
          <tr>
            <td style="background:#ea5500;padding:16px 32px;text-align:center;">
              <p style="margin:0;color:#ffffff;font-size:18px;font-weight:700;letter-spacing:0.02em;">
                🛒 Nueva compra confirmada
              </p>
            </td>
          </tr>

          {{-- Body --}}
          <tr>
            <td style="padding:32px;">

              {{-- Order summary --}}
              <table width="100%" cellpadding="0" cellspacing="0"
                     style="background:#f8fafc;border-radius:10px;padding:20px;margin-bottom:24px;">
                <tr>
                  <td style="font-size:13px;color:#64748b;padding-bottom:6px;">Orden</td>
                  <td style="font-size:14px;font-weight:700;text-align:right;">{{ $invoiceNumber }}</td>
                </tr>
                <tr>
                  <td style="font-size:13px;color:#64748b;padding-bottom:6px;">ID interno</td>
                  <td style="font-size:14px;font-weight:700;text-align:right;">#{{ $orderId }}</td>
                </tr>
                <tr>
                  <td style="font-size:13px;color:#64748b;padding-bottom:6px;">Total</td>
                  <td style="font-size:16px;font-weight:700;color:#ea5500;text-align:right;">{{ $total }}</td>
                </tr>
                @if($paymentBrand || $paymentLast4)
                <tr>
                  <td style="font-size:13px;color:#64748b;padding-bottom:6px;">Pago</td>
                  <td style="font-size:14px;text-align:right;">
                    {{ $paymentBrand ?? '' }}{{ $paymentLast4 ? ' ···· ' . $paymentLast4 : '' }}
                  </td>
                </tr>
                @endif
              </table>

              {{-- Customer info --}}
              <h3 style="margin:0 0 12px;font-size:14px;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">
                Cliente
              </h3>
              <table width="100%" cellpadding="0" cellspacing="0"
                     style="background:#f8fafc;border-radius:10px;padding:20px;margin-bottom:24px;">
                <tr>
                  <td style="font-size:13px;color:#64748b;padding-bottom:6px;">Nombre</td>
                  <td style="font-size:14px;font-weight:600;text-align:right;">{{ $customerName }}</td>
                </tr>
                <tr>
                  <td style="font-size:13px;color:#64748b;padding-bottom:6px;">Email</td>
                  <td style="font-size:14px;text-align:right;">
                    <a href="mailto:{{ $customerEmail }}" style="color:#ea5500;">{{ $customerEmail }}</a>
                  </td>
                </tr>
                @if($shippingAddress)
                <tr>
                  <td style="font-size:13px;color:#64748b;padding-bottom:6px;">Dirección</td>
                  <td style="font-size:13px;text-align:right;">{{ $shippingAddress }}</td>
                </tr>
                @endif
              </table>

              {{-- Products --}}
              <h3 style="margin:0 0 12px;font-size:14px;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">
                Productos ({{ count($products) }})
              </h3>
              @foreach($products as $item)
              @php
                $p = is_array($item) ? ($item['product'] ?? $item) : (isset($item->product) ? $item->product : $item);
                $productName = is_array($p) ? ($p['name'] ?? '—') : ($p->name ?? '—');
                $productId   = is_array($p) ? ($p['id']   ?? '') : ($p->id   ?? '');
                $supplierUrl = is_array($p) ? ($p['supplier_url'] ?? null) : ($p->supplier_url ?? null);
                $qty         = is_array($item) ? ($item['qty'] ?? 1) : ($item->qty ?? 1);
                $unitPrice   = is_array($item) ? ($item['unit_price'] ?? null) : ($item->unit_price ?? null);
              @endphp
              <table width="100%" cellpadding="0" cellspacing="0"
                     style="border:1px solid #e2e8f0;border-radius:10px;margin-bottom:12px;overflow:hidden;">
                <tr>
                  <td style="padding:16px;">
                    <p style="margin:0 0 4px;font-size:14px;font-weight:600;color:#1e293b;">{{ $productName }}</p>
                    <p style="margin:0 0 4px;font-size:12px;color:#64748b;">
                      ID: {{ $productId }} &nbsp;|&nbsp; Qty: {{ $qty }}
                      @if($unitPrice)
                        &nbsp;|&nbsp; Precio unit.: {{ $unitPrice }}
                      @endif
                    </p>
                    @if($supplierUrl)
                    <p style="margin:4px 0 0;">
                      <a href="{{ $supplierUrl }}"
                         style="display:inline-block;background:#ea5500;color:#fff;font-size:12px;font-weight:700;padding:6px 14px;border-radius:6px;text-decoration:none;">
                        Ver en proveedor →
                      </a>
                    </p>
                    @endif
                  </td>
                </tr>
              </table>
              @endforeach

              {{-- CTA --}}
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;">
                <tr>
                  <td align="center">
                    <a href="https://adm.toggolac.com/panel/compras/{{ $orderId }}"
                       style="display:inline-block;background:#ea5500;color:#ffffff;font-size:15px;font-weight:700;padding:14px 32px;border-radius:10px;text-decoration:none;letter-spacing:0.02em;">
                      Gestionar orden #{{ $orderId }}
                    </a>
                  </td>
                </tr>
              </table>

            </td>
          </tr>

          {{-- Footer --}}
          <tr>
            <td style="padding:20px 32px;text-align:center;background:#f8fafc;border-top:1px solid #e2e8f0;">
              <p style="margin:0;font-size:12px;color:#94a3b8;">
                Toggolac Admin &mdash; Notificación automática de compra confirmada
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>
