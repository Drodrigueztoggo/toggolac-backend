    <table>
        <thead>
            <tr>
                <th><strong>#</strong></th>
                <th><strong>Usuario</strong></th>
                <th><strong>Correo electrónico</strong></th>
                <th><strong>Fecha de Creación</strong></th>
                <th><strong>Ciudad</strong></th>
                <th><strong>Número de compras</strong></th>
                <th><strong>Ventas promedio x cita (USD)</strong></th>
                <th><strong>Comisiones</strong></th>
            </tr>
        </thead>
        <tbody>
            @php
             $count = 1;   
            @endphp
            @foreach($data as $user)
                <tr>
                    <td>{{ $count }}</td>
                    <td>{{ $user['name'] }}  {{ $user['last_name'] }}</td>
                    <td>{{ $user['email'] }}</td>
                    <td>{{ $user['created_at'] }}</td>
                    <td>{{ isset($user['city_info']['name']) ? $user['city_info']['name'] : null }}</td>
                    <td>{{ $user['sh_orders_completes_shopper_count'] }}</td>
                    <td>{{ $user['sh_average_sales'] }}</td>
                    <td>{{ $user['sh_commissions_sum_amount'] }}</td> 
                </tr>
            @php
                $count = 1 + $count;   
            @endphp
            @endforeach
        </tbody>
    </table>
