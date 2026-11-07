@php($entregado = $pedido->estado === \App\Enums\EstadoPedido::Entregado)
@php($anulado = $pedido->estado === \App\Enums\EstadoPedido::Anulado)
@php($sinRevisar = $pedido->version > $pedido->version_vista_cocina)
@php($cambios = $pedido->modificaciones->where('version','>',$pedido->version_vista_cocina))
<section @class(['envio-cocina','envio-entregado'=>$entregado,'envio-anulado'=>$anulado,'envio-nuevo'=>$sinRevisar && !$entregado && !$anulado]) data-pedido="{{ $pedido->id }}" data-version="{{ $pedido->version }}">
<div class="titulo-fila"><strong>{{ $esAdicional ? 'Adicional' : 'Pedido' }} #{{ $pedido->id }}</strong><span class="estado {{ $pedido->estado->value }}">{{ $pedido->estado->etiqueta() }}</span></div>
@if(!$entregado && !$anulado)
<div class="tiempo-envio">@include('componentes.tiempo-pedido',['pedido'=>$pedido])<span class="tenue">{{ $pedido->created_at->timezone('America/Lima')->format('H:i') }}</span></div>
@endif
@if($sinRevisar && !$entregado && !$anulado)<span class="etiqueta-novedad">{{ $cambios->isNotEmpty() ? 'Detalle modificado · Revisar cambios' : ($esAdicional ? 'Nuevos platos' : 'Nuevo pedido') }}</span>@endif
<ul class="platos-orden">@foreach($pedido->detalles as $detalle)<li><b>{{ $detalle->cantidad }}×</b><div>{{ $detalle->nombre_producto }}@if($detalle->observaciones)<small class="observacion">{{ $detalle->observaciones }}</small>@endif</div></li>@endforeach</ul>
@if($cambios->isNotEmpty() && !$entregado && !$anulado)
<details class="detalle-cambios" open><summary>Cambios pendientes de revisión</summary>@foreach($cambios as $cambio)<p>{{ $cambio->resumen }}</p>@endforeach</details>
<form method="post" action="{{ route('cocina.reconocer',$pedido) }}">@csrf<input type="hidden" name="version" value="{{ $pedido->version }}"><button class="boton secundario ancho pequeno">Marcar cambios como vistos</button></form>
@endif
@if($pedido->motivo_anulacion)<p class="observacion">{{ $pedido->motivo_anulacion }}</p>@endif
@if(in_array($pedido->estado,[\App\Enums\EstadoPedido::Enviado,\App\Enums\EstadoPedido::EnPreparacion],true))
<form method="post" action="{{ route('cocina.cambiar',$pedido) }}">@csrf @method('PATCH')<input type="hidden" name="estado" value="{{ $pedido->estado === \App\Enums\EstadoPedido::Enviado ? 'preparing' : 'ready' }}"><input type="hidden" name="expected_version" value="{{ $pedido->version }}"><button class="boton ancho {{ $pedido->estado === \App\Enums\EstadoPedido::EnPreparacion ? 'verde':'' }}">{{ $pedido->estado === \App\Enums\EstadoPedido::Enviado ? 'Comenzar preparación':'Marcar como listo' }} →</button></form>
@elseif($pedido->estado === \App\Enums\EstadoPedido::Listo)<p class="listo">✓ Esperando al mozo</p>
@elseif($entregado)<p class="listo">✓ Ya entregado · No volver a preparar</p>@endif
</section>
