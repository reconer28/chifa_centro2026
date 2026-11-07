@if($pedido->pendienteDeEntrega())
<span class="tiempo-pedido" data-demora-pedido="{{ $pedido->id }}" data-inicio="{{ $pedido->created_at->toISOString() }}"><span aria-hidden="true">◷</span> <span data-reloj-pedido>00:00</span><small data-etiqueta-demora>En curso</small></span>
@endif
