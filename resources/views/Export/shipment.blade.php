    <table>
        <thead>
            <tr>
                <th><strong>#</strong></th>

                <th><strong>ID Envío</strong></th>
                <th><strong>ID Orden</strong></th>
                <th><strong>Cliente</strong></th>
                <th><strong>Fecha</strong></th>
                <th><strong>Dirección Origen</strong></th>
                <th><strong>Dirección Destino</strong></th>
                <th><strong>Estado de Envío</strong></th>
                <th><strong>Numero de Seguimiento</strong></th>
                <th><strong>Costo de Envío</strong></th>
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
                    <td>{{ $item['purchase_order_id'] }}</td>
                    <td>{{ isset($item['cliente']['name']) ? $item['cliente']['name'] : null}} {{ isset($item['cliente']['last_name']) ? $item['cliente']['last_name'] : null}}</td>
                    <td>{{ $item['date'] }}</td>
                    <td>
                        {{ $item['origin_address'] }}, 
                        {{ isset($item['origin_country']['name']) ? $item['origin_country']['name'] . ', ': null}} 
                        {{ isset($item['origin_state']['name']) ? $item['origin_state']['name'] . ', ': null}} 
                        {{ isset($item['origin_city']['name']) ? $item['origin_city']['name'] : null}} 
                        
                    </td>
                    <td>
                        {{ $item['destination_address'] }},
                        {{ isset($item['destination_Country']['name']) ? $item['destination_Country']['name'] . ', ': null}} 
                        {{ isset($item['destination_state']['name']) ? $item['destination_state']['name'] . ', ': null}} 
                        {{ isset($item['destination_city']['name']) ? $item['destination_city']['name'] : null}} 
                    </td>
                    <td>{{ isset($item['shipment_status']['name']) ? $item['shipment_status']['name'] : null}}</td>
                    <td>{{ $item['guide_number'] }}</td>
                    <td>{{ $item['total_shipping_cost'] }}</td>

                </tr>
            @php
                $count = 1 + $count;   
            @endphp
            @endforeach
        </tbody>
    </table>
