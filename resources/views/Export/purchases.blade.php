    <table>
        <thead>
            <tr>
                <th><strong>#</strong></th>
                <th><strong>ID de venta</strong></th>
                <th><strong>Total</strong></th>
                <th><strong>Usuario</strong></th>
                <th><strong>Fecha</strong></th>
                <th><strong>Centro comercial</strong></th>
                <th><strong>Estado de Envió</strong></th>
                <th><strong>Estado de Compra</strong></th>
                <th><strong>Personal Shopper</strong></th>
                <th><strong>Ciudad origen</strong></th>
                <th><strong>Ciudad destino</strong></th>
                <th><strong>Fecha Estimada</strong></th>
                <th><strong>Guia</strong></th>
                <th><strong>Nombre Transportadora</strong></th>
            </tr>
        </thead>
        <tbody>
            @php
             $count = 1;   
            @endphp
            @foreach($purchases as $purchase)
                <tr>
                    <td>{{ $count }}</td>
                    <td>{{$purchase['id']}}</td>
                    <td>{{$purchase['total_product']['formatted']}}</td>
                    <td>{{$purchase['user']}}</td>
                    <td>{{$purchase['start_date']}}</td>
                    <td>{{$purchase['mall']}}</td>
                    <td>{{isset($purchase['shipment_status']) ? $purchase['shipment_status']['name'] : null}}</td>
                    <td>{{$purchase['purchase_status']}}</td>
                    <td>{{isset($purchase['personal_shopper_info']) ? $purchase['personal_shopper_info']['name'] : null}}</td>
                    <td>{{isset($purchase['origin']['city']) ? $purchase['origin']['city']['name'] : null}}</td>
                    <td>{{$purchase['destination_city']}}</td>
                    <td>{{$purchase['estimated_date']}}</td>
                    <td>{{$purchase['guide_number']}}</td>
                    <td>{{$purchase['carriers']}}</td>
                </tr>
            @php
                $count = 1 + $count;   
            @endphp
            @endforeach
        </tbody>
    </table>
