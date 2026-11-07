@extends('layouts.panel')
@section('contenido')
<div class="titulo-fila acciones-superiores"><div class="pestanas"><a @class(['activa'=>!$historial]) href="{{ route('cocina.index') }}">En curso</a><a @class(['activa'=>$historial==='delivered']) href="{{ route('cocina.index',['historial'=>'delivered']) }}">Entregados</a><a @class(['activa'=>$historial==='cancelled']) href="{{ route('cocina.index',['historial'=>'cancelled']) }}">Anulados</a></div><div><span class="en-vivo" id="estado-tablero" role="status">● Actualización cada 5 segundos</span> <a class="boton secundario pequeno" href="{{ request()->fullUrl() }}">Actualizar</a></div></div>
<div data-tablero data-url="{{ request()->fullUrl() }}">@include('cocina.columnas')</div>
@endsection
