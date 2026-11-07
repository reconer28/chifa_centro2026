<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Atencion;
use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdministracionWebTest extends TestCase
{
    private function administrador(): Usuario
    {
        $usuario = Usuario::factory()->create(['rol' => RolUsuario::Administrador]);
        $this->actingAs($usuario, 'web');

        return $usuario;
    }

    public function test_ingreso_y_salida_del_administrador(): void
    {
        $usuario = Usuario::factory()->create(['rol' => RolUsuario::Administrador]);
        $this->get('/')->assertRedirect(route('login'));
        $this->post('/ingresar', ['usuario' => $usuario->usuario, 'password' => 'incorrecta'])->assertSessionHasErrors('usuario');
        $this->assertGuest('web');
        $this->post('/ingresar', ['usuario' => $usuario->usuario, 'password' => 'clave123'])->assertRedirect(route('admin.resumen'));
        $this->assertAuthenticatedAs($usuario, 'web');
        $this->post('/salir')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_todas_las_pantallas_de_administracion_se_renderizan(): void
    {
        $this->administrador();
        foreach (['/administracion', '/administracion/pedidos', '/administracion/reportes', '/cocina', '/administracion/soporte/productos', '/administracion/soporte/categorias', '/administracion/soporte/usuarios', '/administracion/soporte/empleados', '/administracion/soporte/mesas'] as $ruta) {
            $this->get($ruta)->assertOk();
        }
    }

    public function test_mozo_y_cocina_no_administran_y_la_api_no_acepta_solo_cookie_web(): void
    {
        foreach ([RolUsuario::Mozo, RolUsuario::Cocina] as $rol) {
            $usuario = Usuario::factory()->create(['rol' => $rol]);
            $this->actingAs($usuario, 'web')->get('/administracion')->assertForbidden();
        }
        $this->actingAs(Usuario::factory()->create(), 'web')->getJson('/api/v1/menu')->assertUnauthorized();
    }

    public function test_crea_edita_y_desactiva_producto_con_imagen_y_precio_exacto(): void
    {
        $this->administrador();
        Storage::fake('productos');
        $categoria = Categoria::factory()->create();
        $nombre = 'Producto de prueba '.Str::random(10);
        $datos = ['nombre' => $nombre, 'categoria_id' => $categoria->id, 'precio' => '18.51', 'stock' => 10, 'descripcion' => 'Al wok', 'disponible' => 1, 'destacado' => 0, 'activo' => 1, 'imagen' => UploadedFile::fake()->image('plato.jpg')];
        $this->post(route('admin.soporte.guardar', ['tipo' => 'productos']), $datos)->assertSessionHasNoErrors()->assertRedirect();
        $producto = Producto::query()->where('nombre', $nombre)->firstOrFail();
        $this->assertSame(1851, $producto->precio_centimos);
        Storage::disk('productos')->assertExists(basename($producto->imagen));
        unset($datos['imagen']);
        $datos['precio'] = '20.03';
        $this->post(route('admin.soporte.guardar', ['tipo' => 'productos', 'registro' => $producto->id]), $datos)->assertSessionHasNoErrors();
        $this->assertSame(2003, $producto->fresh()->precio_centimos);
        $this->patch(route('admin.soporte.disponibilidad', ['tipo' => 'productos', 'registro' => $producto->id]))->assertRedirect();
        $this->assertFalse($producto->fresh()->activo);
    }

    public function test_rechaza_archivos_que_no_son_imagenes_y_precio_invalido(): void
    {
        $this->administrador();
        $categoria = Categoria::factory()->create();
        $this->post(route('admin.soporte.guardar', ['tipo' => 'productos']), ['nombre' => 'Prueba', 'categoria_id' => $categoria->id, 'precio' => '1.999', 'stock' => -1, 'disponible' => 1, 'destacado' => 0, 'activo' => 1, 'imagen' => UploadedFile::fake()->create('archivo.php', 1, 'application/x-httpd-php')])->assertSessionHasErrors(['imagen', 'precio', 'stock']);
    }

    public function test_crea_empleado_y_usuario_en_una_misma_operacion(): void
    {
        $this->administrador();
        $nombre = 'prueba_'.strtolower(Str::random(12));
        $this->post(route('admin.soporte.guardar', ['tipo' => 'empleados']), ['nombres' => 'Empleado', 'apellidos' => 'De prueba', 'dni' => '', 'telefono' => '999999999', 'cargo' => 'Mozo', 'activo' => 1, 'crear_usuario' => 1, 'usuario' => $nombre, 'password' => 'clave123', 'password_confirmation' => 'clave123', 'rol' => 'Mozo'])->assertSessionHasNoErrors();
        $cuenta = Usuario::query()->where('usuario', $nombre)->firstOrFail();
        $this->assertNotNull($cuenta->empleado_id);
        $this->assertSame('Empleado', $cuenta->empleado->nombres);
        $this->assertSame('clave123', $cuenta->password);
    }

    public function test_editar_usuario_sin_clave_la_conserva_y_cambiarla_revoca_tokens(): void
    {
        $this->administrador();
        $cuenta = Usuario::factory()->create();
        $cuenta->createToken('Android');
        $datos = ['nombre' => 'Mozo actualizado', 'usuario' => $cuenta->usuario, 'rol' => 'Mozo', 'activo' => 1, 'password' => '', 'password_confirmation' => ''];
        $ruta = route('admin.soporte.guardar', ['tipo' => 'usuarios', 'registro' => $cuenta->id]);
        $this->post($ruta, $datos)->assertSessionHasNoErrors();
        $this->assertSame('clave123', $cuenta->fresh()->password);
        $this->assertSame(1, $cuenta->tokens()->count());
        $datos['password'] = $datos['password_confirmation'] = 'claveNueva123';
        $this->post($ruta, $datos)->assertSessionHasNoErrors();
        $this->assertSame('claveNueva123', $cuenta->fresh()->password);
        $this->assertSame(0, $cuenta->tokens()->count());
    }

    public function test_no_permite_autodesactivarse_ni_desactivar_mozo_o_mesa_ocupados(): void
    {
        $admin = $this->administrador();
        $this->patch(route('admin.soporte.disponibilidad', ['tipo' => 'usuarios', 'registro' => $admin->id]))->assertSessionHasErrors('activo');
        $mozo = Usuario::factory()->create();
        $mesa = Mesa::factory()->create(['mozo_id' => $mozo->id]);
        Atencion::factory()->create(['mesa_id' => $mesa->id, 'mozo_id' => $mozo->id]);
        $this->patch(route('admin.soporte.disponibilidad', ['tipo' => 'usuarios', 'registro' => $mozo->id]))->assertSessionHasErrors('activo');
        $this->patch(route('admin.soporte.disponibilidad', ['tipo' => 'mesas', 'registro' => $mesa->id]))->assertSessionHasErrors('activo');
        $this->assertTrue($mesa->fresh()->activo);
        $this->assertTrue($mozo->fresh()->activo);
    }

    public function test_categoria_y_mesa_pueden_crearse_y_editarse(): void
    {
        $this->administrador();
        $nombre = 'Categoria prueba '.Str::random(10);
        $this->post(route('admin.soporte.guardar', ['tipo' => 'categorias']), ['nombre' => $nombre, 'activo' => 1])->assertSessionHasNoErrors();
        $categoria = Categoria::query()->where('nombre', $nombre)->firstOrFail();
        $producto = Producto::factory()->create(['categoria_id' => $categoria->id]);
        $this->post(route('admin.soporte.guardar', ['tipo' => 'categorias', 'registro' => $categoria->id]), ['nombre' => $nombre.' nueva', 'activo' => 1])->assertSessionHasNoErrors();
        $this->assertSame($nombre.' nueva', $producto->fresh()->getRawOriginal('categoria'));
        $this->post(route('admin.soporte.guardar', ['tipo' => 'mesas']), ['numero' => 65000, 'capacidad' => 6, 'zona' => 'Zona de prueba', 'activo' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mesas', ['numero' => 65000, 'capacidad' => 6]);
    }

    public function test_productos_filtra_por_categoria_y_nombre_y_conserva_la_paginacion(): void
    {
        $this->administrador();
        $categoria = Categoria::factory()->create();
        $otraCategoria = Categoria::factory()->create();
        $busqueda = 'Filtro '.Str::random(12);
        Producto::factory()->count(13)->create(['categoria_id' => $categoria->id, 'nombre' => $busqueda]);
        Producto::factory()->create(['categoria_id' => $otraCategoria->id, 'nombre' => $busqueda]);

        $ruta = route('admin.soporte', ['tipo' => 'productos']);
        $respuesta = $this->get($ruta.'?'.http_build_query(['q' => $busqueda, 'categoria_id' => $categoria->id]))
            ->assertOk()->assertSee('Todas las categorías');
        $registros = $respuesta->viewData('registros');
        $this->assertSame(13, $registros->total());
        $this->assertCount(12, $registros->items());
        $this->assertTrue($registros->getCollection()->every(fn (Producto $producto): bool => $producto->categoria_id === $categoria->id));
        $this->assertStringContainsString('categoria_id='.$categoria->id, $registros->nextPageUrl());
        parse_str(parse_url($registros->nextPageUrl(), PHP_URL_QUERY), $parametros);
        $this->assertSame($busqueda, $parametros['q']);
        $this->get($registros->nextPageUrl())->assertOk()->assertViewHas('registros', fn ($pagina): bool => $pagina->total() === 13 && $pagina->count() === 1);

        $this->get($ruta.'?'.http_build_query(['q' => $busqueda]))->assertOk()
            ->assertViewHas('registros', fn ($pagina): bool => $pagina->total() === 14);
        $this->get($ruta.'?'.http_build_query(['categoria_id' => $otraCategoria->id]))->assertOk()
            ->assertViewHas('registros', fn ($pagina): bool => $pagina->total() === 1);
        $this->get($ruta.'?'.http_build_query(['q' => $busqueda.' inexistente', 'categoria_id' => $categoria->id]))->assertOk()
            ->assertViewHas('registros', fn ($pagina): bool => $pagina->total() === 0);
        $this->get($ruta.'?categoria_id=invalida')->assertSessionHasErrors('categoria_id');
    }
}
