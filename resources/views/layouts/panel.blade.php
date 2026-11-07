<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $titulo ?? 'Chifa Centro' }} · Chifa Centro</title><link rel="stylesheet" href="{{ asset('css/panel.css') }}"></head>
@php($esCocina = request()->routeIs('cocina.*'))
@php($enTiempoReal = request()->routeIs('cocina.*','admin.resumen','admin.pedidos','admin.detalle'))
<body @class(['vista-cocina' => $esCocina])>
<aside class="lateral" id="menu-lateral"><a class="marca" href="{{ route('inicio') }}"><span class="sello">中</span><span>CHIFA CENTRO<small>Tradición que se comparte</small></span></a>
<p class="leyenda">ESPACIO DE TRABAJO</p><nav>
@if(auth()->user()->rol === \App\Enums\RolUsuario::Administrador)
<a @class(['seleccionado'=>request()->routeIs('admin.resumen')]) href="{{ route('admin.resumen') }}">Resumen</a>
<a @class(['seleccionado'=>request()->routeIs('admin.pedidos','admin.detalle')]) href="{{ route('admin.pedidos') }}">Pedidos y cobros</a>
@foreach(['productos'=>'Productos','categorias'=>'Categorías','empleados'=>'Empleados','usuarios'=>'Usuarios','mesas'=>'Mesas'] as $ruta=>$nombre)
<a @class(['seleccionado'=>request()->route('tipo') === $ruta]) href="{{ route('admin.soporte',['tipo'=>$ruta]) }}">{{ $nombre }}</a>
@endforeach
<a @class(['seleccionado'=>request()->routeIs('admin.reportes')]) href="{{ route('admin.reportes') }}">Reportes</a>
@endif
@if(in_array(auth()->user()->rol,[\App\Enums\RolUsuario::Administrador,\App\Enums\RolUsuario::Cocina],true))
<a @class(['seleccionado'=>request()->routeIs('cocina.*')]) href="{{ route('cocina.index') }}">Cocina</a>
@endif
</nav><div class="cuenta"><strong>{{ auth()->user()->nombre }}</strong><small>{{ auth()->user()->rol->value }}</small><form method="post" action="{{ route('sesion.salir') }}">@csrf<button class="enlace" type="submit">Cerrar sesión →</button></form></div></aside>
<main class="principal"><header class="cabecera"><div class="grupo-cabecera">
@if($esCocina)
<button type="button" class="control-menu" data-alternar-menu aria-controls="menu-lateral" aria-expanded="true">
<span aria-hidden="true">&#9776;</span><span data-etiqueta-menu>Ocultar menú</span>
</button>
@endif
<div><p class="leyenda">CHIFA CENTRO / {{ auth()->user()->rol->value }}</p><h1>{{ $titulo ?? 'Bienvenido' }}</h1></div></div><span class="fecha">{{ now('America/Lima')->translatedFormat('d/m/Y · H:i') }}</span></header>
@if(session('exito'))<div class="aviso exito" role="status">{{ session('exito') }}</div>@endif
@if($errors->any())<div class="aviso error" role="alert"><strong>Revisa los datos</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($enTiempoReal)
<section class="barra-tiempo-real" data-tiempo-real data-servidor-ahora="{{ now()->valueOf() }}" data-reverb-key="{{ config('broadcasting.connections.reverb.key') }}" data-reverb-host="{{ config('chifa.websocket_host') ?: request()->getHost() }}" data-reverb-port="{{ config('chifa.websocket_port') }}" data-reverb-scheme="{{ config('chifa.websocket_scheme') }}" data-amarillo="{{ config('chifa.advertencia_minutos') }}" data-rojo="{{ config('chifa.urgencia_minutos') }}">
<span id="estado-conexion" class="estado-conexion" role="status">Conectando avisos...</span>
<button type="button" class="boton secundario pequeno" data-activar-sonido aria-pressed="false">Activar sonido</button>
<span class="tenue" data-resumen-demoras></span><small class="tenue" data-ultimo-aviso></small>
</section>
<div class="aviso aviso-pedido" data-aviso-pedido role="status" aria-live="polite" hidden><span data-texto-aviso></span><button type="button" class="enlace" data-cerrar-aviso aria-label="Cerrar aviso">&times;</button></div>
@endif
<div @if($enTiempoReal) data-contenido-pedidos data-servidor-ahora="{{ now()->valueOf() }}" @endif>
@yield('contenido')
</div>
<footer>Chifa Centro <span>Hecho para atender mejor.</span></footer></main>
<script src="{{ asset('js/panel.js') }}" defer></script>@if($enTiempoReal) @vite('resources/js/tiempo_real.js') @endif @stack('scripts')
</body></html>
