<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoPedido;
use App\Http\Controllers\Controller;
use App\Http\Resources\MesaResource;
use App\Http\Resources\PedidoResource;
use App\Http\Resources\ProductoResource;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConsultaController extends Controller
{
    public function mesas(): AnonymousResourceCollection
    {
        $mesas = Mesa::query()->where('activo', true)->with([
            'atencionActiva' => fn (HasOne $consulta) => $consulta->withSum([
                'pedidos as total_atencion' => fn (Builder $pedidos) => $pedidos->where('estado', '!=', EstadoPedido::Anulado->value),
            ], 'total'),
        ])->orderBy('numero')->get();

        return MesaResource::collection($mesas);
    }

    public function carta(): AnonymousResourceCollection
    {
        $productos = Producto::query()->where('activo', true)->whereHas('categoriaProducto', fn (Builder $consulta) => $consulta->where('activo', true))->with('categoriaProducto')->orderBy('categoria_id')->orderBy('nombre')->get();

        return ProductoResource::collection($productos);
    }

    public function pedidos(Request $solicitud): AnonymousResourceCollection
    {
        $pedidos = Pedido::query()->where('mozo_id', $solicitud->user()->id)->with(['atencion.mesa', 'detalles'])->latest('id')->get();

        return PedidoResource::collection($pedidos);
    }
}
