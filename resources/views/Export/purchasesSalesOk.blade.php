    <table>
        <thead>
            <tr>
                <th><strong>#</strong></th>
                <th><strong>ID de venta</strong></th>
                <th><strong>Fecha</strong></th>
                <th><strong>Producto</strong></th>
                <th><strong>Marcas</strong></th>
                <th><strong>Categoria</strong></th>
                <th><strong>Tienda Vendedor</strong></th>
                <th><strong>Centro Comercial</strong></th>
                <th><strong>Cantidad</strong></th>
                <th><strong>Precio Unitario</strong></th>
                <th><strong>Total</strong></th>
            </tr>
        </thead>
        <tbody>
            @php
             $count = 1;   
            @endphp
            @foreach($purchases as $purchase)
                <tr>
                    <td style="vertical-align: top;">{{ $count }}</td>
                    <td style="vertical-align: top;">{{$purchase['id']}}</td>
                    <td style="vertical-align: top;">{{$purchase['created_at']}}</td>
                    <td style="vertical-align: top;">
                        @if (isset($purchase['purchase_order_details']))
                            @foreach ($purchase['purchase_order_details'] as $product)
                            • @if (isset($product['product']['name']))
                                    {{ $product['product']['name'] }}
                                @endif
                                @unless ($loop->last)
                                    <br>
                                @endunless
                            @endforeach 
                        @endif
                    </td>
                    <td style="vertical-align: top;">
                        @if (isset($purchase['purchase_order_details']))
                            @foreach ($purchase['purchase_order_details'] as $product)
                            • @if (isset($product['product']['brand']['name_brand']))
                                    {{ $product['product']['brand']['name_brand'] }}
                                @endif
                                @unless ($loop->last)
                                    <br>
                                @endunless
                            @endforeach 
                        @endif
                    </td>
                    <td style="vertical-align: top;">
                        @if (isset($purchase['purchase_order_details']))
                            @foreach ($purchase['purchase_order_details'] as $product)
                            • @if (isset($product['product']['categories']))
                                @foreach ($product['product']['categories'] as $categorie)
                                    @if (isset($categorie['name']))
                                        {{ $categorie['name'] }}
                                        @unless ($loop->last)
                                            ,
                                        @endunless
                                    @endif
                                @endforeach 
                            @endif
                            @unless ($loop->last)
                                <br>
                            @endunless
                            @endforeach 
                        @endif
                    </td>
                    <td style="vertical-align: top;">
                        @if (isset($purchase['purchase_order_details']))
                            @foreach ($purchase['purchase_order_details'] as $product)
                            • @if (isset($product['store']['name']))
                                    {{ $product['store']['name'] }}   
                                @endif
                                @unless ($loop->last)
                                    <br>
                                @endunless
                            @endforeach 
                        @endif
                    </td>
                    <td style="vertical-align: top;">{{$purchase['mall']}}</td>
                    <td style="vertical-align: top;">
                        @if (isset($purchase['purchase_order_details']))
                            @foreach ($purchase['purchase_order_details'] as $product)
                            • @if (isset($product['amount']))
                                    {{ $product['amount'] }} 
                                @endif
                                @unless ($loop->last)
                                    <br>
                                @endunless
                            @endforeach 
                        @endif
                    </td>
                    <td style="vertical-align: top;">
                        @if (isset($purchase['purchase_order_details']))
                            @foreach ($purchase['purchase_order_details'] as $product)
                            • @if (isset($product['price']))
                                    {{ $product['price'] }} 
                                @endif
                                @unless ($loop->last)
                                    <br>
                                @endunless
                            @endforeach 
                        @endif
                    </td>
                    <td style="vertical-align: top;">{{$purchase['total_product']['formatted']}}</td>
                </tr>
            @php
                $count = 1 + $count;   
            @endphp
            @endforeach
        </tbody>
    </table>
