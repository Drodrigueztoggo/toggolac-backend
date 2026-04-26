<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9pt;
    color: #000;
    width: 288pt;
    height: 432pt;
  }
  .label {
    width: 288pt;
    height: 432pt;
    border: 2pt solid #000;
    display: flex;
    flex-direction: column;
  }
  .header {
    background: #000;
    color: #fff;
    text-align: center;
    padding: 6pt 8pt;
    font-size: 11pt;
    font-weight: bold;
    letter-spacing: 1pt;
  }
  .order-ref {
    background: #f0f0f0;
    border-bottom: 1pt solid #000;
    padding: 4pt 8pt;
    font-size: 8pt;
    display: flex;
    justify-content: space-between;
  }
  .order-ref strong { font-size: 10pt; }
  .section {
    padding: 6pt 8pt;
    border-bottom: 1pt solid #000;
  }
  .section-title {
    font-size: 7pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    color: #555;
    margin-bottom: 3pt;
  }
  .address-name {
    font-size: 11pt;
    font-weight: bold;
    margin-bottom: 2pt;
  }
  .address-line {
    font-size: 9pt;
    line-height: 1.4;
  }
  .details-row {
    display: flex;
    padding: 6pt 8pt;
    border-bottom: 1pt solid #000;
    gap: 8pt;
  }
  .detail-box {
    flex: 1;
    text-align: center;
  }
  .detail-label {
    font-size: 7pt;
    color: #555;
    text-transform: uppercase;
    margin-bottom: 2pt;
  }
  .detail-value {
    font-size: 12pt;
    font-weight: bold;
  }
  .barcode-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 8pt;
    text-align: center;
  }
  .barcode-text {
    font-family: 'Courier New', monospace;
    font-size: 14pt;
    font-weight: bold;
    letter-spacing: 3pt;
    margin-bottom: 4pt;
    border: 1pt solid #000;
    padding: 6pt 12pt;
  }
  .barcode-sub {
    font-size: 8pt;
    color: #555;
  }
  .footer {
    background: #f0f0f0;
    border-top: 1pt solid #000;
    padding: 4pt 8pt;
    font-size: 7pt;
    text-align: center;
    color: #555;
  }
</style>
</head>
<body>
<div class="label">

  {{-- Header --}}
  <div class="header">TOGGOLAC — ENVÍO COLOMBIA</div>

  {{-- Order reference --}}
  <div class="order-ref">
    <span>Orden: <strong>{{ $shipment->purchase_order_id }}</strong></span>
    <span>Fecha: {{ \Carbon\Carbon::parse($shipment->date)->format('d/m/Y') }}</span>
  </div>

  {{-- FROM --}}
  <div class="section">
    <div class="section-title">Remitente / From</div>
    <div class="address-name">{{ config('services.shippo.origin_name', 'Toggolac') }}</div>
    <div class="address-line">{{ config('services.shippo.origin_street') }}</div>
    <div class="address-line">
      {{ config('services.shippo.origin_city') }}, {{ config('services.shippo.origin_state') }} {{ config('services.shippo.origin_zip') }}
    </div>
    <div class="address-line">United States</div>
  </div>

  {{-- TO --}}
  <div class="section">
    <div class="section-title">Destinatario / To</div>
    <div class="address-name">{{ $shipment->customer_name_lastname }}</div>
    <div class="address-line">{{ $shipment->destination_address }}</div>
    <div class="address-line">
      {{ $destinationCity }}{{ $destinationCity && $destinationState ? ', ' : '' }}{{ $destinationState }}
      @if($shipment->destination_postal_code) {{ $shipment->destination_postal_code }} @endif
    </div>
    <div class="address-line">{{ $destinationCountry }}</div>
  </div>

  {{-- Weight + dimensions --}}
  <div class="details-row">
    <div class="detail-box">
      <div class="detail-label">Peso</div>
      <div class="detail-value">{{ number_format($shipment->pounds_weight, 1) }} lb</div>
    </div>
    @if($shipment->package_length)
    <div class="detail-box">
      <div class="detail-label">Dimensiones (in)</div>
      <div class="detail-value">
        {{ $shipment->package_length }}×{{ $shipment->package_width }}×{{ $shipment->package_height }}
      </div>
    </div>
    @endif
    <div class="detail-box">
      <div class="detail-label">Envío</div>
      <div class="detail-value">ID #{{ $shipment->id }}</div>
    </div>
  </div>

  {{-- Reference barcode area --}}
  <div class="barcode-area">
    <div class="barcode-text">TGG-{{ str_pad($shipment->id, 8, '0', STR_PAD_LEFT) }}</div>
    <div class="barcode-sub">Número de referencia — entregar al transportador</div>
  </div>

  {{-- Footer --}}
  <div class="footer">
    toggolac.com &nbsp;|&nbsp; info@toggolac.com &nbsp;|&nbsp; Impreso: {{ now()->format('d/m/Y H:i') }}
  </div>

</div>
</body>
</html>
