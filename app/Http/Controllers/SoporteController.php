<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarSoporteRequest;
use App\Models\Categoria;
use App\Models\Empleado;
use App\Services\GestionSoporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class SoporteController extends Controller
{
    public function __construct(private GestionSoporte $gestion) {}

    public function index(Request $solicitud, string $tipo): View
    {
        $datos = $solicitud->validate(['q' => ['nullable', 'string', 'max:100'], 'editar' => ['nullable', 'integer', 'min:1'], ...($tipo === 'productos' ? ['categoria_id' => ['nullable', 'integer', 'exists:categorias,id']] : [])]);
        $configuracion = $this->gestion->tipos()[$tipo] ?? abort(404);
        $consulta = $this->gestion->modelo($tipo)->newQuery();
        if (! empty($datos['q'])) {
            $consulta->where(function (Builder $busqueda) use ($configuracion, $datos): void {
                foreach ($configuracion['buscar'] as $campo) {
                    $busqueda->orWhere($campo, 'like', '%'.$datos['q'].'%');
                }
            });
        }
        if ($tipo === 'productos') {
            if (! empty($datos['categoria_id'])) {
                $consulta->where('categoria_id', $datos['categoria_id']);
            }
            $consulta->with('categoriaProducto');
        }
        if ($tipo === 'usuarios') {
            $consulta->with('empleado');
        }
        if ($tipo === 'mesas') {
            $consulta->with(['mozo', 'atencionActiva']);
        }
        $registros = $consulta->orderBy($tipo === 'mesas' ? 'numero' : 'id')->paginate(12)->withQueryString();
        $registro = ! empty($datos['editar']) ? $this->gestion->modelo($tipo)->newQuery()->findOrFail($datos['editar']) : null;

        return view('administracion.soporte', ['tipo' => $tipo, 'titulo' => $configuracion['titulo'], 'registros' => $registros, 'registro' => $registro, 'categorias' => Categoria::query()->orderBy('nombre')->get(), 'empleados' => Empleado::query()->orderBy('apellidos')->get()]);
    }

    public function guardar(GuardarSoporteRequest $solicitud, string $tipo, ?int $registro = null): RedirectResponse
    {
        $datos = $solicitud->validated();
        $archivo = null;
        if ($solicitud->hasFile('imagen')) {
            $archivo = Storage::disk('productos')->putFile('', $solicitud->file('imagen'));
            $datos['imagen'] = 'uploads/productos/'.$archivo;
        } else {
            unset($datos['imagen']);
        }
        try {
            $this->gestion->guardar($solicitud->user(), $tipo, $datos, $registro);
        } catch (Throwable $error) {
            if ($archivo) {
                Storage::disk('productos')->delete($archivo);
            }
            throw $error;
        }

        return redirect()->route('admin.soporte', ['tipo' => $tipo])->with('exito', 'Registro guardado correctamente.');
    }

    public function disponibilidad(Request $solicitud, string $tipo, int $registro): RedirectResponse
    {
        $this->gestion->cambiarDisponibilidad($solicitud->user(), $tipo, $registro);

        return redirect()->route('admin.soporte', ['tipo' => $tipo])->with('exito', 'Disponibilidad actualizada.');
    }
}
