<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutenticacionApiTest extends TestCase
{
    private function datosDeAcceso(Usuario $usuario, string $contrasena = 'clave123'): array
    {
        return ['username' => $usuario->usuario, 'password' => $contrasena, 'device_name' => 'Android de prueba'];
    }

    public function test_el_mozo_ingresa_y_recibe_el_formato_esperado_por_flutter(): void
    {
        $usuario = Usuario::factory()->create();
        $respuesta = $this->postJson('/api/v1/auth/login', $this->datosDeAcceso($usuario));
        $respuesta->assertOk()->assertJsonPath('data.user.id', (string) $usuario->id)->assertJsonPath('data.user.name', $usuario->nombre)->assertJsonPath('data.user.username', $usuario->usuario)->assertJsonMissingPath('data.user.password');
        $token = $respuesta->json('data.token');
        $this->assertIsString($token);
        $registro = DB::table('personal_access_tokens')->where('tokenable_id', $usuario->id)->first();
        $this->assertNotSame($token, $registro->token);
        $this->assertNotNull($registro->expires_at);
        $this->withToken($token)->getJson('/api/v1/menu')->assertOk();
        $this->assertArrayNotHasKey('password', $usuario->toArray());
    }

    public function test_rechaza_clave_incorrecta_usuario_inexistente_e_inactivo(): void
    {
        $usuario = Usuario::factory()->create();
        $inactivo = Usuario::factory()->create(['activo' => false]);
        $this->postJson('/api/v1/auth/login', $this->datosDeAcceso($usuario, 'incorrecta'))->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['username' => 'prueba_inexistente', 'password' => 'clave123', 'device_name' => 'Android'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', $this->datosDeAcceso($inactivo))->assertUnauthorized();
        $this->assertSame(0, DB::table('personal_access_tokens')->whereIn('tokenable_id', [$usuario->id, $inactivo->id])->count());
    }

    public function test_administrador_y_cocina_no_pueden_ingresar_como_mozos(): void
    {
        foreach ([RolUsuario::Administrador, RolUsuario::Cocina] as $rol) {
            $usuario = Usuario::factory()->create(['rol' => $rol]);
            $this->postJson('/api/v1/auth/login', $this->datosDeAcceso($usuario))->assertForbidden();
            $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $usuario->id)->count());
        }
    }

    public function test_valida_campos_y_devuelve_json_sin_cabecera_accept(): void
    {
        $this->post('/api/v1/auth/login', [])->assertUnprocessable()->assertJsonValidationErrors(['username', 'password', 'device_name']);
        $this->postJson('/api/v1/auth/login', ['username' => ['dato'], 'password' => 123, 'device_name' => str_repeat('a', 101)])->assertUnprocessable()->assertJsonValidationErrors(['username', 'password', 'device_name']);
    }

    public function test_limita_intentos_de_acceso(): void
    {
        $usuario = Usuario::factory()->create();
        for ($intento = 0; $intento < 5; $intento++) {
            $this->postJson('/api/v1/auth/login', $this->datosDeAcceso($usuario, 'incorrecta'))->assertUnauthorized();
        }
        $this->postJson('/api/v1/auth/login', $this->datosDeAcceso($usuario, 'incorrecta'))->assertTooManyRequests()->assertHeader('Retry-After');
    }

    public function test_el_cierre_revoca_solo_el_token_actual(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('Primer celular')->plainTextToken;
        $otroToken = $usuario->createToken('Segundo celular')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/menu')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($otroToken)->getJson('/api/v1/menu')->assertOk();
        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $usuario->id)->count());
    }

    public function test_rechaza_token_vencido_o_invalido(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('Android', ['app:mozo'], now()->subMinute())->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/menu')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken('999999999|invalido')->getJson('/api/v1/menu')->assertUnauthorized();
    }

    public function test_desactivar_o_cambiar_rol_bloquea_un_token_existente(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('Android')->plainTextToken;
        $usuario->update(['activo' => false]);
        $this->withToken($token)->getJson('/api/v1/menu')->assertUnauthorized();
        $usuario->update(['activo' => true, 'rol' => RolUsuario::Cocina]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/menu')->assertForbidden();
    }

    public function test_el_usuario_mozo_existente_puede_consultar_el_catalogo_real(): void
    {
        $usuario = Usuario::query()->where('rol', RolUsuario::Mozo->value)->where('activo', true)->firstOrFail();
        $respuesta = $this->postJson('/api/v1/auth/login', $this->datosDeAcceso($usuario, $usuario->password))->assertOk();
        $this->withToken($respuesta->json('data.token'))->getJson('/api/v1/menu')->assertOk()->assertJsonStructure(['data' => [['id', 'name', 'category', 'price_cents', 'available']]]);
    }
}
