<table>
        <thead>
            <tr>
                <th><strong>#</strong></th>
                <th><strong>Marca</strong></th>
                <th><strong>Descripción</strong></th>
                <th><strong>Pais</strong></th>
                <th><strong>Ciudad</strong></th>
                <th><strong>Categoria</strong></th>
                <th><strong>Tiendas</strong></th>
            </tr>
        </thead>
        <tbody>
            @php
             $count = 1;   
            @endphp
            @foreach($data as $item)
                <tr>
                    <td>{{ $count }}</td>
                    <td>{{ $item['name_brand'] }}</td>
                    <td>{{ $item['description_brand'] }}</td>
                    <td>{{ isset($item['country']['name']) ? $item['country']['name'] : null }}</td>
                    <td>{{ isset($item['city']['name']) ? $item['city']['name'] : null }}</td>

                    <td>
                    
                    @if (isset($item['categories']))
                        @foreach ($item['categories'] as $category)
                        @if (isset($category['name']))
                             {{ $category['name'] }}
                            @unless ($loop->last)
                                ,
                            @endunless
                        @endif
                           
                        @endforeach 
                    @endif
                        
                    </td>
                    <td>
                    
                    @if (isset($item['store_mall']))
                        @foreach ($item['store_mall'] as $store)
                        @if (isset($store['name']))
                             {{ $store['name'] }}
                            @unless ($loop->last)
                                ,
                            @endunless
                        @endif
                           
                        @endforeach 
                    @endif
                        
                    </td>

                </tr>
            @php
                $count = 1 + $count;   
            @endphp
            @endforeach
        </tbody>
    </table>
