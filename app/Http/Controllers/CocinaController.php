<?php

namespace App\Http\Controllers;

use App\Enums\EstadoPedido;
use App\Http\Requests\CambiarEstadoRequest;
use App\Models\Pedido;
use App\Services\GestionPedidos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CocinaController extends Controller
{
    public function index(Request $solicitud): View|Response
    {
        $datos = $solicitud->validate(['historial' => ['nullable', 'in:delivered,cancelled']]);
        $historial = $datos['historial'] ?? null;
        $consulta = Pedido::query()->with(['atencion.mesa', 'mozoUsuario', 'detalles']);
        if ($historial) {
            $consulta->where('estado', $historial);
        } else {
            $consulta->whereIn('estado', ['sent', 'preparing', 'ready'])->whereHas('atencion', fn ($atencion) => $atencion->whereNull('fecha_cierre'));
        }
        $pedidos = $historial ? $consulta->latest('id')->paginate(30)->withQueryString() : $consulta->oldest('id')->get();
        $datos = ['titulo' => 'Cocina', 'pedidos' => $pedidos, 'historial' => $historial];
        if ($solicitud->header('X-Tablero') === '1') {
            return response(view('cocina.columnas', $datos)->render())->header('Cache-Control', 'no-store');
        }

        return view('cocina.tablero', $datos);
    }

    public function cambiar(CambiarEstadoRequest $solicitud, Pedido $pedido, GestionPedidos $gestion): RedirectResponse
    {
        $gestion->avanzarCocina($pedido->id, EstadoPedido::from($solicitud->validated('estado')));

        return redirect()->route('cocina.index')->with('exito', 'Estado del pedido actualizado.');
    }
}
