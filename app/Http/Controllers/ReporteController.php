<?php

namespace App\Http\Controllers;

use App\Enums\RolUsuario;
use App\Http\Requests\ReporteRequest;
use App\Models\Producto;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReporteController extends Controller
{
    public function index(ReporteRequest $solicitud): View
    {
        $datos = $solicitud->validated();
        $desde = CarbonImmutable::parse($datos['desde'] ?? now('America/Lima')->startOfMonth()->toDateString(), 'America/Lima')->startOfDay();
        $hasta = CarbonImmutable::parse($datos['hasta'] ?? now('America/Lima')->toDateString(), 'America/Lima')->endOfDay();
        if ($desde->greaterThan($hasta) || $desde->diffInDays($hasta) > 366) {
            throw ValidationException::withMessages(['hasta' => 'Selecciona un rango de hasta un año con fechas válidas.']);
        }
        $base = DB::table('detalle_pedido as detalle')->join('pedidos as pedido', 'pedido.id', '=', 'detalle.pedido_id')->where('pedido.estado', 'delivered')->whereBetween('pedido.created_at', [$desde->utc(), $hasta->utc()]);
        if (! empty($datos['mozo_id'])) {
            $base->where('pedido.mozo_id', $datos['mozo_id']);
        }
        if (! empty($datos['producto_id'])) {
            $base->where('detalle.producto_id', $datos['producto_id']);
        }
        $dias = (clone $base)->selectRaw("DATE(CONVERT_TZ(pedido.created_at, '+00:00', '-05:00')) AS dia, SUM(detalle.precio_centimos * detalle.cantidad) AS total_centimos, COUNT(DISTINCT pedido.id) AS cantidad")->groupBy('dia')->orderByDesc('dia')->get();
        $platos = (clone $base)->selectRaw('detalle.producto_id, detalle.nombre_producto, SUM(detalle.cantidad) AS cantidad, SUM(detalle.precio_centimos * detalle.cantidad) AS total_centimos')->groupBy('detalle.producto_id', 'detalle.nombre_producto')->orderByDesc('cantidad')->limit(10)->get();
        $cobrados = (clone $base)->where('pedido.estado_cobro', 'Pagado')->sum(DB::raw('detalle.precio_centimos * detalle.cantidad'));

        return view('administracion.reportes', ['titulo' => 'Reportes de ventas', 'dias' => $dias, 'platos' => $platos, 'total' => $dias->sum('total_centimos'), 'cobrados' => $cobrados, 'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'mozos' => Usuario::query()->where('rol', RolUsuario::Mozo->value)->orderBy('nombre')->get(), 'productos' => Producto::query()->orderBy('nombre')->get()]);
    }
}
