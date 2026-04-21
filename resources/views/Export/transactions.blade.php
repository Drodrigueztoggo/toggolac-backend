    <table>
        <thead>
            <tr>
                <th><strong>#</strong></th>

                <th><strong>ID Transacción</strong></th>
                <th><strong>ID Payment</strong></th>
                <th><strong>Nombre Cliente</strong></th>
                <th><strong>Personal Shopper</strong></th>
                <th><strong>Fecha y Hora</strong></th>
                <th><strong>Monto (USD)</strong></th>
                <th><strong>Pais</strong></th>
                <th><strong>Ciudad</strong></th>
                <th><strong>Productos</strong></th>
                <th><strong>Método de Pago</strong></th>
                <th><strong>Estado de la transacción</strong></th>
            </tr>
        </thead>
        <tbody>
            @php
             $count = 1;   
            @endphp
            @foreach($data as $item)
                <tr>
                    <td>{{ $count }}</td>

                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['payment_id'] }}</td>
                    <td>{{ isset($item['info_order']['client']['name']) ? $item['info_order']['client']['name'] : null}} {{ isset($item['info_order']['client']['last_name']) ? $item['info_order']['client']['last_name'] : null}}</td>
                    <td>{{ isset($item['info_order']['personal_shopper']['name']) ? $item['info_order']['personal_shopper']['name'] : null}} {{ isset($item['info_order']['personal_shopper']['last_name']) ? $item['info_order']['personal_shopper']['last_name'] : null}}</td>
                    <td>{{ $item['created_date'] }}</td>
                    <td>{{ $item['amount'] }}</td>
                    <td>{{ isset($item['info_order']['destination_country']['name']) ? $item['info_order']['destination_country']['name'] : null}}</td>
                    <td>{{ isset($item['info_order']['destination_city']['name']) ? $item['info_order']['destination_city']['name'] : null}}</td>
                    <td>
                        @if (isset($item['info_order']['products']))
                            @foreach ($item['info_order']['products'] as $product)
                            @if (isset($product['product']['name_product']))
                                 {{ $product['product']['name_product'] }}
                                @unless ($loop->last)
                                    ,
                                @endunless
                            @endif
                               
                            @endforeach 
                        @endif
                    </td>
                    <td>{{ $item['payment_method_type'] }}</td>
                    <td>{{ $item['status'] }}</td>
                </tr>
            @php
                $count = 1 + $count;   
            @endphp
            @endforeach
        </tbody>
    </table>
