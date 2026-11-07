@extends('layouts.panel')
@section('contenido')
<div class="distribucion">
<section class="tarjeta listado"><div class="titulo-fila"><div><h2>{{ $titulo }}</h2><p class="tenue">{{ $registros->total() }} registros en tu restaurante</p></div></div>
<form method="get" @class(['buscador', 'buscador-productos' => $tipo === 'productos'])>
<input name="q" value="{{ request('q') }}" placeholder="Buscar en {{ mb_strtolower($titulo) }}..." aria-label="Buscar">
@if($tipo === 'productos')
<select name="categoria_id" aria-label="Filtrar por categoría">
<option value="">Todas las categorías</option>
@foreach($categorias as $categoria)
<option value="{{ $categoria->id }}" @selected((string) request('categoria_id') === (string) $categoria->id)>{{ $categoria->nombre }}</option>
@endforeach
</select>
@endif
<button class="boton secundario">Buscar</button>
</form>
@if($tipo === 'productos')<div class="productos">
@forelse($registros as $fila)<article class="producto"><div class="imagen-producto">@if($fila->imagen)<img src="{{ asset($fila->imagen) }}" alt="{{ $fila->nombre }}" loading="lazy" onerror="this.hidden=true">@endif<span class="fondo-plato" aria-hidden="true">中</span></div><div class="producto-cuerpo"><span class="etiqueta">{{ $fila->categoriaProducto?->nombre }}</span><h3>{{ $fila->nombre }}</h3><p class="tenue">{{ $fila->stock === null ? 'Sin límite de unidades' : $fila->stock.' unidades disponibles' }}</p><div class="titulo-fila"><strong>S/ {{ number_format($fila->precio_centimos/100,2) }}</strong><span class="estado {{ $fila->activo && $fila->estaDisponible() ? 'ready' : 'cancelled' }}">{{ ! $fila->activo ? 'Inactivo' : ($fila->estaDisponible() ? 'Disponible' : 'Agotado') }}</span></div><div class="acciones"><a href="{{ route('admin.soporte',['tipo'=>$tipo,'editar'=>$fila->id]) }}">Editar</a><form method="post" action="{{ route('admin.soporte.disponibilidad',['tipo'=>$tipo,'registro'=>$fila->id]) }}">@csrf @method('PATCH')<button class="enlace">{{ $fila->activo ? 'Desactivar' : 'Activar' }}</button></form></div></div></article>
@empty<div class="vacio"><h3>No encontramos productos</h3><p>Agrega uno desde el formulario o cambia la búsqueda.</p></div>@endforelse</div>
@else<div class="tabla-contenedor"><table><thead><tr><th>Registro</th><th>Detalle</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($registros as $fila)<tr><td><strong>{{ match($tipo) {'empleados'=>$fila->nombres.' '.$fila->apellidos,'usuarios'=>$fila->nombre,'mesas'=>'Mesa '.$fila->numero,default=>$fila->nombre} }}</strong>@if($tipo === 'usuarios')<small>{{ $fila->usuario }}</small>@endif</td><td>
@switch($tipo)
@case('empleados'){{ $fila->dni ?: 'Sin DNI' }}<small>{{ $fila->cargo ?: 'Sin cargo' }}</small>@break
@case('usuarios'){{ $fila->rol->value }}<small>{{ $fila->empleado?->nombres ?: 'Sin empleado asociado' }}</small>@break
@case('mesas'){{ $fila->zona }} · {{ $fila->capacidad }} personas<small>{{ $fila->atencionActiva ? 'Ocupada' : 'Libre' }} · {{ $fila->mozo && $fila->asignacionVigente() ? $fila->mozo->nombre : 'Sin asignar' }}</small>@break
@default #{{ $fila->id }}
@endswitch
</td><td><span class="estado {{ $fila->activo ? 'ready':'cancelled' }}">{{ $fila->activo ? 'Activo':'Inactivo' }}</span></td><td><div class="acciones"><a href="{{ route('admin.soporte',['tipo'=>$tipo,'editar'=>$fila->id]) }}">Editar</a><form method="post" action="{{ route('admin.soporte.disponibilidad',['tipo'=>$tipo,'registro'=>$fila->id]) }}">@csrf @method('PATCH')<button class="enlace">{{ $fila->activo ? 'Desactivar':'Activar' }}</button></form></div></td></tr>
@empty<tr><td colspan="4"><div class="vacio">No hay registros para esta búsqueda.</div></td></tr>@endforelse
</tbody></table></div>@endif
@if($registros->hasPages())<nav class="paginacion">@if($registros->previousPageUrl())<a href="{{ $registros->previousPageUrl() }}">← Anterior</a>@endif<span>Página {{ $registros->currentPage() }} de {{ $registros->lastPage() }}</span>@if($registros->nextPageUrl())<a href="{{ $registros->nextPageUrl() }}">Siguiente →</a>@endif</nav>@endif
</section>
<aside class="tarjeta editor" id="editor"><div class="titulo-fila"><h2>{{ $registro ? 'Editar registro':'Nuevo registro' }}</h2>@if($registro)<a href="{{ route('admin.soporte',['tipo'=>$tipo]) }}">Cancelar</a>@endif</div>
@php($valor = fn($campo,$defecto='') => old($campo,$registro?->{$campo} ?? $defecto))
<form method="post" enctype="multipart/form-data" action="{{ route('admin.soporte.guardar',['tipo'=>$tipo,'registro'=>$registro?->id]) }}" class="formulario">@csrf
@switch($tipo)
@case('productos')
<label>Nombre del producto<input name="nombre" value="{{ $valor('nombre') }}" maxlength="100" required></label>
<label>Categoría<select name="categoria_id" required><option value="">Selecciona una categoría</option>@foreach($categorias as $categoria)<option value="{{ $categoria->id }}" @selected((string)$valor('categoria_id') === (string)$categoria->id)>{{ $categoria->nombre }}{{ $categoria->activo ? '':' (inactiva)' }}</option>@endforeach</select></label>
<div class="dos-campos"><label>Precio (S/)<input type="number" name="precio" value="{{ old('precio',$registro?->precio) }}" step=".01" min="0" max="99999999.99" required></label><label>Unidades<input type="number" name="stock" value="{{ $valor('stock') }}" min="0" placeholder="Sin límite"></label></div>
<label>Descripción<textarea name="descripcion" rows="3" maxlength="2000">{{ $valor('descripcion') }}</textarea></label>
<label>Imagen<input type="file" name="imagen" accept="image/jpeg,image/png,image/webp,image/gif,image/avif"><small>JPG, PNG, WEBP, GIF o AVIF. Hasta 4 MB.</small></label>
@foreach(['disponible'=>'Disponible para pedir','destacado'=>'Destacar en la carta'] as $campo=>$etiqueta)<input type="hidden" name="{{ $campo }}" value="0"><label class="casilla"><input type="checkbox" name="{{ $campo }}" value="1" @checked((bool)$valor($campo,$campo === 'disponible'))>{{ $etiqueta }}</label>@endforeach
@break
@case('categorias')<label>Nombre de la categoría<input name="nombre" value="{{ $valor('nombre') }}" maxlength="50" required></label>@break
@case('mesas')<div class="dos-campos"><label>Número de mesa<input name="numero" type="number" value="{{ $valor('numero') }}" min="1" max="65535" required></label><label>Capacidad<input name="capacidad" type="number" value="{{ $valor('capacidad',4) }}" min="1" max="50" required></label></div><label>Zona<input name="zona" value="{{ $valor('zona','Salón principal') }}" maxlength="100" required></label><p class="nota">El mozo se asigna sus mesas desde la app con autorización del administrador.</p>@break
@case('empleados')
<label>Nombres<input name="nombres" value="{{ $valor('nombres') }}" maxlength="100" required></label><label>Apellidos<input name="apellidos" value="{{ $valor('apellidos') }}" maxlength="100" required></label><div class="dos-campos"><label>DNI<input name="dni" value="{{ $valor('dni') }}" maxlength="8" inputmode="numeric"></label><label>Teléfono<input name="telefono" value="{{ $valor('telefono') }}" maxlength="20"></label></div><label>Cargo<input name="cargo" value="{{ $valor('cargo') }}" maxlength="50"></label>
@if(! $registro)<input type="hidden" name="crear_usuario" value="0"><label class="casilla"><input type="checkbox" name="crear_usuario" value="1" data-cuenta @checked(old('crear_usuario'))>Crear también su usuario</label>@endif
@break
@case('usuarios')
<label>Nombre a mostrar<input name="nombre" value="{{ $valor('nombre') }}" maxlength="150" required></label>
<label>Empleado asociado<select name="empleado_id"><option value="">Sin empleado asociado</option>@foreach($empleados as $empleado)<option value="{{ $empleado->id }}" @selected((string)$valor('empleado_id') === (string)$empleado->id)>{{ $empleado->nombres }} {{ $empleado->apellidos }}{{ $empleado->activo ? '':' (inactivo)' }}</option>@endforeach</select></label>
@break
@endswitch
@if($tipo === 'usuarios' || ($tipo === 'empleados' && ! $registro))
<fieldset @if($tipo === 'empleados') data-campos-cuenta @endif><label>Usuario<input name="usuario" value="{{ old('usuario',$tipo === 'usuarios' ? $registro?->usuario : '') }}" maxlength="50" autocomplete="off" required></label><label>Rol<select name="rol">@foreach(\App\Enums\RolUsuario::cases() as $rol)<option value="{{ $rol->value }}" @selected(old('rol',$tipo === 'usuarios' ? $registro?->rol?->value : 'Mozo') === $rol->value)>{{ $rol->value }}</option>@endforeach</select></label><label>{{ $registro ? 'Nueva contraseña (opcional)':'Contraseña' }}<input type="password" name="password" autocomplete="new-password" minlength="6" maxlength="255" @required(! $registro)></label><label>Repite la contraseña<input type="password" name="password_confirmation" autocomplete="new-password" @required(! $registro)></label>@if($registro)<p class="nota">Deja ambos campos vacíos para conservar la contraseña actual.</p>@endif</fieldset>
@endif
<input type="hidden" name="activo" value="0"><label class="casilla"><input type="checkbox" name="activo" value="1" @checked((bool)$valor('activo',true))>Registro activo</label>
<button class="boton ancho" type="submit">{{ $registro ? 'Guardar cambios':'Crear registro' }}</button></form></aside></div>
@endsection
