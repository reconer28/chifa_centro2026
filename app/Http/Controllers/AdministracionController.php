<?php

namespace App\Http\Controllers;

use App\Enums\EstadoPedido;
use App\Models\Atencion;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\GestionPedidos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdministracionController extends Controller
{
    public function resumen(): View
    {
        return view('administracion.resumen', ['titulo' => 'Resumen del restaurante', 'productos' => Producto::query()->where('activo', true)->count(), 'ocupadas' => Atencion::query()->whereNull('fecha_cierre')->count(), 'pendientes' => Pedido::query()->whereIn('estado', ['sent', 'preparing', 'ready'])->count(), 'cobrar' => Pedido::query()->where('estado', EstadoPedido::Entregado->value)->where('estado_cobro', 'Pendiente')->sum('total'), 'recientes' => Pedido::query()->with(['atencion.mesa', 'mozoUsuario'])->latest('id')->limit(8)->get()]);
    }

    public function pedidos(Request $solicitud): View
    {
        $datos = $solicitud->validate(['estado' => ['nullable', Rule::enum(EstadoPedido::class)], 'cobro' => ['nullable', Rule::in(['Pendiente', 'Pagado'])]]);
        $consulta = Pedido::query()->with(['atencion.mesa', 'mozoUsuario']);
        if (! empty($datos['estado'])) {
            $consulta->where('estado', $datos['estado']);
        }
        if (! empty($datos['cobro'])) {
            $consulta->where('estado_cobro', $datos['cobro']);
        }

        return view('administracion.pedidos', ['titulo' => 'Pedidos y cobros', 'pedidos' => $consulta->latest('id')->paginate(15)->withQueryString()]);
    }

    public function detalle(Pedido $pedido): View
    {
        return view('administracion.detalle', ['titulo' => 'Pedido #'.$pedido->id, 'pedido' => $pedido->load(['atencion.mesa', 'mozoUsuario', 'detalles'])]);
    }

    public function cobrar(Request $solicitud, Pedido $pedido, GestionPedidos $gestion): RedirectResponse
    {
        $gestion->cobrar($solicitud->user(), $pedido->id);

        return back()->with('exito','Cobro registrado correctamente.');
    }
}
