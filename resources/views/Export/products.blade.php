    <table>
        <thead>
            <tr>
                <th><strong>#</strong></th>

                <th><strong>ID Producto</strong></th>
                <th><strong>Nombre</strong></th>
                <th><strong>Precio</strong></th>
                <th><strong>Descripción</strong></th>
                <th><strong>Ciudad</strong></th>
                <th><strong>Marca</strong></th>
                <th><strong>Categoría</strong></th>
                <th><strong>Centro Comercial</strong></th>
                <th><strong>Tienda Vendedor</strong></th>
                <th><strong>Peso(lb)</strong></th>
                <th><strong>Fecha Creación</strong></th>
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
                    <td>{{ $item['name_product'] }}</td>  
                    <td>
                        {{ isset($item['price']['min']) ? $item['price']['min'] . ' - ': 0 . ' - '}}
                        {{ isset($item['price']['max']) ? $item['price']['max'] : 0 }}
                    </td>  
                    <td>{{ $item['description_product'] }}</td>  
                    <td>
                        
                        @if (isset($item['cities']))
                            @foreach ($item['cities'] as $city)
                            @if (isset($city['name']))
                                {{ $city['name'] }}
                            @endif
                            @unless ($loop->last)
                            ,
                            @endunless
                            @endforeach
                        @endif
                        
                    </td>  
                    <td>{{ isset($item['brand']) ? $item['brand']['name_brand'] : null }}</td>  
                    <td>
                        
                        @if (isset($item['categories']))
                            @foreach ($item['categories'] as $category)
                            @if (isset($category['name']))
                                {{ $category['name'] }}
                            @endif
                            @unless ($loop->last)
                            ,
                            @endunless
                            @endforeach
                        @endif
                        
                    </td>  
                    <td>
                        
                        @if (isset($item['mall_products']))
                            @foreach ($item['mall_products'] as $mall)
                            • @if (isset($mall['name']))
                                {{ $mall['name'] }}
                            @endif
                            @unless ($loop->last)
                            <br>
                            @endunless
                            @endforeach
                        @endif
                        
                    </td>  
                    <td>
                        
                        @if (isset($item['store_products']))
                            @foreach ($item['store_products'] as $store)
                            • @if (isset($store['name']))
                                {{ $store['name'] }}
                            @endif
                            @unless ($loop->last)
                            <br>
                            @endunless
                            @endforeach
                        @endif
                        
                    </td> 
                    <td>{{ $item['weight'] }}</td>  
                    <td>{{ $item['created_at'] }}</td>  
 
                </tr>
            @php
                $count = 1 + $count;   
            @endphp
            @endforeach
        </tbody>
    </table>
