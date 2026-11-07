<?php

namespace App\Http\Requests;

use App\Enums\RolUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarSoporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('registro');
        $comunes = ['activo' => ['required', 'boolean']];
        $cuenta = [
            'usuario' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('usuarios', 'usuario')->ignore($this->route('tipo') === 'usuarios' ? $id : null)],
            'password' => [$id && $this->route('tipo') === 'usuarios' ? 'nullable' : 'required', 'string', 'min:6', 'max:255', 'confirmed'],
            'rol' => ['required', Rule::enum(RolUsuario::class)],
        ];

        return match ($this->route('tipo')) {
            'productos' => [...$comunes, 'nombre' => ['required', 'string', 'max:100'], 'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->where('activo', true)], 'precio' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'], 'stock' => ['nullable', 'integer', 'between:0,2147483647'], 'descripcion' => ['nullable', 'string', 'max:2000'], 'disponible' => ['required', 'boolean'], 'destacado' => ['required', 'boolean'], 'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:4096']],
            'categorias' => [...$comunes, 'nombre' => ['required', 'string', 'max:50', Rule::unique('categorias', 'nombre')->ignore($id)]],
            'mesas' => [...$comunes, 'numero' => ['required', 'integer', 'between:1,65535', Rule::unique('mesas', 'numero')->ignore($id)], 'capacidad' => ['required', 'integer', 'between:1,50'], 'zona' => ['required', 'string', 'max:100']],
            'usuarios' => [...$comunes, ...$cuenta, 'nombre' => ['required', 'string', 'max:150'], 'empleado_id' => ['nullable', 'integer', Rule::exists('empleados', 'id')->where('activo', true), Rule::unique('usuarios', 'empleado_id')->ignore($id)]],
            'empleados' => [...$comunes, 'nombres' => ['required', 'string', 'max:100'], 'apellidos' => ['required', 'string', 'max:100'], 'dni' => ['nullable', 'digits:8', Rule::unique('empleados', 'dni')->ignore($id)], 'telefono' => ['nullable', 'string', 'max:20'], 'cargo' => ['nullable', 'string', 'max:50'], 'crear_usuario' => ['nullable', 'boolean'], ...($this->boolean('crear_usuario') && ! $id ? $cuenta : [])],
            default => [],
        };
    }

    public function messages(): array
    {
        return ['required' => 'El campo :attribute es obligatorio.', 'unique' => 'Ya existe un registro con ese :attribute.', 'exists' => 'La selección de :attribute no está disponible.', 'confirmed' => 'Las contraseñas no coinciden.', 'min' => 'El campo :attribute debe tener al menos :min caracteres.', 'max' => 'El campo :attribute supera el límite permitido (:max).', 'regex' => 'El formato de :attribute no es válido.', 'image' => 'Selecciona una imagen válida.', 'mimes' => 'La imagen debe ser JPG, PNG, WEBP, GIF o AVIF.', 'between' => 'El valor de :attribute debe estar entre :min y :max.', 'digits' => 'El DNI debe tener 8 dígitos.'];
    }
}
