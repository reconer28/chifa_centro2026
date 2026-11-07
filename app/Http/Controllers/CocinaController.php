<?php

namespace App\Http\Controllers;

use App\Enums\EstadoPedido;
use App\Http\Requests\CambiarEstadoRequest;
use App\Http\Requests\ReconocerCambioRequest;
use App\Models\Atencion;
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
        $atenciones = collect();
        $pedidos = collect();
        if ($historial) {
            $pedidos = Pedido::query()->where('estado', $historial)->with(['atencion.mesa', 'mozoUsuario', 'detalles', 'modificaciones'])->latest('id')->paginate(30)->withQueryString();
        } else {
            $atenciones = Atencion::query()->whereNull('fecha_cierre')->whereHas('pedidos', fn ($consulta) => $consulta->whereIn('estado', ['sent', 'preparing', 'ready']))->with(['mesa', 'mozo', 'pedidos' => fn ($consulta) => $consulta->with(['detalles', 'modificaciones'])->orderBy('id')])->orderBy('id')->get();
            foreach ($atenciones as $atencion) {
                foreach ($atencion->pedidos as $pedido) {
                    $pedido->setRelation('atencion', $atencion);
                }
                $estados = $atencion->pedidos->pluck('estado');
                $columna = $estados->contains(EstadoPedido::Enviado) ? 'sent' : ($estados->contains(EstadoPedido::EnPreparacion) ? 'preparing' : 'ready');
                $atencion->setAttribute('columna_cocina', $columna);
            }
        }
        $datos = ['titulo' => 'Cocina', 'atenciones' => $atenciones, 'pedidos' => $pedidos, 'historial' => $historial];
        if ($solicitud->header('X-Tablero') === '1') {
            return response(view('cocina.columnas', $datos)->render())->header('Cache-Control', 'no-store');
        }

        return view('cocina.tablero', $datos);
    }

    public function cambiar(CambiarEstadoRequest $solicitud, Pedido $pedido, GestionPedidos $gestion): RedirectResponse
    {
        $gestion->avanzarCocina($pedido->id, EstadoPedido::from($solicitud->validated('estado')), $solicitud->integer('expected_version'));

        return redirect()->route('cocina.index')->with('exito', 'Estado del pedido actualizado.');
    }

    public function reconocer(ReconocerCambioRequest $solicitud, Pedido $pedido, GestionPedidos $gestion): RedirectResponse
    {
        $gestion->reconocerCambio($pedido->id, $solicitud->integer('version'));

        return redirect()->route('cocina.index')->with('exito', 'Cambios del pedido revisados.');
    }
}
