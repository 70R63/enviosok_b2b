<div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
    <h4>TIPO DE ENVIO </h4>
    <div class="card custom-card">
        {!! Form::hidden('tipo_envio', 1 ?? 0,
            ['class'        => 'form-control'
            ,'style'        =>  'display:visible;'
            ])
        !!}
        {!! Form::hidden('clave', $clave ?? 0,
            ['class'        => 'form-control'
                ,'id'   => 'clave'  
                ,'style'        =>  'display:visible;'
            ])
        !!}

        @switch(3)
            @case(1)
                @include('guia.crear.paquete.sobre')
                @break

            @case(2)
                @include('guia.crear.paquete.pieza')
                @break

            @case(3)
                @include('guia.crear.paquete.multipieza')
                @break
        @endswitch
        
    </div>
</div>
