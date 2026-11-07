<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnularPedidoRequest;
use App\Http\Requests\AsignarMesasRequest;
use App\Http\Requests\CerrarMesaRequest;
use App\Http\Requests\EnviarPedidoRequest;
use App\Http\Resources\PedidoResource;
use App\Services\GestionPedidos;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PedidoController extends Controller
{
    public function __construct(private GestionPedidos $gestion) {}

    public function asignar(AsignarMesasRequest $solicitud): Response
    {
        $datos = $solicitud->validated();
        $this->gestion->asignarMesas($solicitud->user(), $datos['table_ids'], $datos['admin_password']);

        return response()->noContent();
    }

    public function enviar(EnviarPedidoRequest $solicitud): PedidoResource
    {
        $datos = $solicitud->validated();

        return new PedidoResource($this->gestion->enviar($solicitud->user(), $datos, $datos['clave_idempotencia']));
    }

    public function entregar(Request $solicitud, int $pedido): Response
    {
        $this->gestion->entregar($solicitud->user(), $pedido);

        return response()->noContent();
    }

    public function anular(AnularPedidoRequest $solicitud, int $pedido): Response
    {
        $datos = $solicitud->validated();
        $this->gestion->anular($solicitud->user(), $pedido, $datos['reason'], $datos['admin_password']);

        return response()->noContent();
    }

    public function cerrar(CerrarMesaRequest $solicitud, int $mesa): Response
    {
        $this->gestion->cerrar($solicitud->user(), $mesa, $solicitud->validated('order_ids'));

        return response()->noContent();
    }
}
