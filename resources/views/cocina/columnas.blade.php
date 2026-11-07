@php($columnas = $historial ? [$historial => $historial === 'delivered' ? 'Entregados' : 'Anulados'] : ['sent'=>'Pendientes','preparing'=>'En preparación','ready'=>'Listos para entregar'])
<div @class(['columnas-cocina','historial'=>!!$historial])>
@foreach($columnas as $estado=>$nombre)
@php($grupo = $historial ? $pedidos : $atenciones->where('columna_cocina',$estado))
<section class="columna-cocina {{ $estado }}"><div class="titulo-columna"><h2>{{ $nombre }}</h2><span>{{ $grupo->count() }}</span></div><div class="lista-cocina">
@forelse($grupo as $elemento)
@if($historial)
<article class="orden"><div class="titulo-fila"><strong>Mesa {{ $elemento->atencion->mesa->numero }}</strong><span>#{{ $elemento->id }}</span></div><p class="tenue">{{ $elemento->mozoUsuario->nombre }}</p>@include('cocina.envio',['pedido'=>$elemento,'esAdicional'=>false])</article>
@else
<article class="orden" data-atencion="{{ $elemento->id }}"><div class="titulo-fila"><strong>Mesa {{ $elemento->mesa->numero }}</strong><span>Atención #{{ $elemento->id }}</span></div><p class="tenue">{{ $elemento->mozo->nombre }} · {{ $elemento->fecha_apertura->timezone('America/Lima')->format('d/m H:i') }}</p>
@foreach($elemento->pedidos as $pedido)
@include('cocina.envio',['pedido'=>$pedido,'esAdicional'=>!$loop->first])
@endforeach
</article>
@endif
@empty<div class="vacio"><span>◌</span><p>{{ $historial ? 'Sin pedidos en este historial.' : 'Sin pedidos por ahora.' }}</p></div>@endforelse
</div></section>
@endforeach</div>
@if($historial && $pedidos->hasPages())<nav class="paginacion">@if($pedidos->previousPageUrl())<a href="{{ $pedidos->previousPageUrl() }}">← Anterior</a>@endif<span>Página {{ $pedidos->currentPage() }}</span>@if($pedidos->nextPageUrl())<a href="{{ $pedidos->nextPageUrl() }}">Siguiente →</a>@endif</nav>@endif
