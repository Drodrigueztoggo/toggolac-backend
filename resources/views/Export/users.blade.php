    <table>
        <thead>
            <tr>
                <th><strong>#</strong></th>
                <th><strong>Nombre</strong></th>
                <th><strong>Email</strong></th>
                <th><strong>Fecha de Creación</strong></th>
                <th><strong>País</strong></th>
                <th><strong>Ciudad</strong></th>
                <th><strong>Edad</strong></th>
                <th><strong>Número de Compras</strong></th>
                <th><strong>Total de Compras</strong></th>
            </tr>
        </thead>
        <tbody>
            @php
             $count = 1;   
            @endphp
            @foreach($users as $user)
                <tr>
                    <td>{{ $count }}</td>
                    <td>{{ $user['name'] }}</td>
                    <td>{{ $user['email'] }}</td>
                    <td>{{ $user['created_at'] }}</td>
                    <td>{{ $user['country'] }}</td>
                    <td>{{ $user['city'] }}</td>
                    <td>{{ $user['age'] }}</td>
                    <td>{{ $user['num_purchases'] }}</td>
                    <td>{{ $user['total_amount_purchases'] }}</td> 
                </tr>
            @php
                $count = 1 + $count;   
            @endphp
            @endforeach
        </tbody>
    </table>
