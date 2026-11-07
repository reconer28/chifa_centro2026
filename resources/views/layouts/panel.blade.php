<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $titulo ?? 'Chifa Centro' }} · Chifa Centro</title><link rel="stylesheet" href="{{ asset('css/panel.css') }}"></head>
@php($esCocina = request()->routeIs('cocina.*'))
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
@yield('contenido')
<footer>Chifa Centro <span>Hecho para atender mejor.</span></footer></main>
<script src="{{ asset('js/panel.js') }}" defer></script>@stack('scripts')
</body></html>
