<?php

use App\Enums\RolUsuario;
use App\Models\Usuario;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('restaurante.pedidos', fn (Usuario $usuario): bool => $usuario->activo && in_array($usuario->rol, [RolUsuario::Administrador, RolUsuario::Cocina], true));
