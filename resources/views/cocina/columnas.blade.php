@php($columnas = $historial ? [$historial => $historial === 'delivered' ? 'Entregados' : 'Anulados'] : ['sent'=>'Pendientes','preparing'=>'En preparación','ready'=>'Listos para entregar'])
<div @class(['columnas-cocina','historial'=>!!$historial])>
@foreach($columnas as $estado=>$nombre)
@php($grupo = $historial ? $pedidos : $pedidos->where('estado',\App\Enums\EstadoPedido::from($estado)))
<section class="columna-cocina {{ $estado }}"><div class="titulo-columna"><h2>{{ $nombre }}</h2><span>{{ $grupo->count() }}</span></div><div class="lista-cocina">
@forelse($grupo as $pedido)<article class="orden"><div class="titulo-fila"><strong>Mesa {{ $pedido->atencion->mesa->numero }}</strong><span>#{{ $pedido->id }}</span></div><p class="tenue">{{ $pedido->mozoUsuario->nombre }} · {{ $pedido->created_at->timezone('America/Lima')->format('d/m H:i') }}</p>
<ul class="platos-orden">@foreach($pedido->detalles as $detalle)<li><b>{{ $detalle->cantidad }}×</b><div>{{ $detalle->nombre_producto }}@if($detalle->observaciones)<small class="observacion">{{ $detalle->observaciones }}</small>@endif</div></li>@endforeach</ul>
@if($pedido->motivo_anulacion)<p class="observacion">{{ $pedido->motivo_anulacion }}</p>@endif
@if(in_array($estado,['sent','preparing'],true))<form method="post" action="{{ route('cocina.cambiar',$pedido) }}">@csrf @method('PATCH')<input type="hidden" name="estado" value="{{ $estado === 'sent' ? 'preparing' : 'ready' }}"><button class="boton ancho {{ $estado === 'preparing' ? 'verde':'' }}">{{ $estado === 'sent' ? 'Comenzar preparación':'Marcar como listo' }} →</button></form>@elseif($estado === 'ready')<p class="listo">✓ Esperando al mozo</p>@endif
</article>@empty<div class="vacio"><span>◌</span><p>{{ $historial ? 'Sin pedidos en este historial.' : 'Sin pedidos por ahora.' }}</p></div>@endforelse</div></section>
@endforeach</div>
@if($historial && $pedidos->hasPages())<nav class="paginacion">@if($pedidos->previousPageUrl())<a href="{{ $pedidos->previousPageUrl() }}">← Anterior</a>@endif<span>Página {{ $pedidos->currentPage() }}</span>@if($pedidos->nextPageUrl())<a href="{{ $pedidos->nextPageUrl() }}">Siguiente →</a>@endif</nav>@endif
