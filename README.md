# Chifa Centro — Laravel y API Android

Migración del sistema PHP a Laravel 13 / PHP 8.3, utilizando MySQL de Laragon y la base existente **bd_chifa_centro**. Se conservaron los usuarios, empleados, 195 productos, precios y columnas anteriores. Las fotos disponibles en el ZIP se trasladaron a public/uploads/productos; los productos sin foto muestran una ilustración.

## Iniciar el sitio

En una terminal de Laragon con PHP 8.3, dentro del proyecto:

```powershell
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Abrir http://127.0.0.1:8000. También puede utilizarse el dominio configurado por Laragon cuando tenga su host virtual registrado. Se conservan las credenciales de la base anterior. Administrador ingresa al panel; Cocina, al tablero. El rol Mozo toma pedidos en Android y ve una página informativa al ingresar por web.

## Módulos trasladados

- Administración: resumen, categorías, productos con imágenes, precios, stock, disponibilidad, empleados, usuarios por rol y mesas.
- Empleados: opción de crear su usuario en la misma operación. Cambiar una contraseña revoca tokens del celular.
- Los registros se desactivan y pueden reactivarse. No se borra el historial de pedidos.
- Cocina: pendientes, preparación, listos, entregados y anulados. Recibe novedades mediante Reverb y Echo, sin consultas programadas cada cinco segundos.
- Pedidos y cobros: detalle con observaciones, precios históricos, registro de pago e impresión del resumen.
- Reportes: ventas entregadas por fecha de Lima, mozo y producto; platos vendidos y cobros de esas ventas. El reporte no cuenta pedidos anulados ni platos todavía en preparación.
- App: asignación autorizada, pedidos, edición inicial, adicionales, entrega, anulación y cierre de atención.

La asignación usa como turno el día en Lima. Una mesa con atención abierta conserva su mozo aunque cambie el día. Las mesas libres de un turno anterior pueden reasignarse con autorización. No se transfieren mesas ocupadas.

## API para Flutter

Base de prueba en la PC: http://127.0.0.1:8000/api/v1.

| Método | Ruta | Datos |
|---|---|---|
| POST | /auth/login | username, password, device_name |
| POST | /auth/logout | Revoca el token actual |
| GET | /tables | Mesas, asignaciones, ocupación y totales |
| GET | /menu | Carta, precios en céntimos y disponibilidad |
| GET | /orders | Pedidos del mozo autenticado |
| PUT | /me/tables | table_ids, admin_password |
| POST | /orders | table_id, items con product_id, quantity, notes |
| PUT | /orders/{id} | expected_version, items e Idempotency-Key |
| POST | /orders/{id}/deliver | Marcar entregado |
| POST | /orders/{id}/cancel | reason, admin_password |
| POST | /tables/{id}/close | order_ids |

Cabeceras: Accept: application/json y Authorization: Bearer TOKEN. Al crear un pedido, enviar Idempotency-Key con la clave persistida por Flutter. Respuestas bajo data; errores en message. Importes enteros en céntimos y fechas UTC ISO 8601.

Precios y mozo se obtienen del servidor. Stock se descuenta en la misma transacción; la anulación lo devuelve una sola vez. Repetir una clave devuelve el mismo pedido, incluso después de cerrar su atención. Otra carga con la misma clave se rechaza con 409. El cierre compara todos los order_ids y un reintento anterior no afecta una atención nueva. Cierre y cobro son independientes.

La API acepta únicamente tokens de mozos. La web utiliza sesiones y protección CSRF. Autorizaciones fallidas tienen límite de intentos y registro sin guardar la clave. Cocina no crea usuarios ni registra cobros.

## Pruebas en la base solicitada

```powershell
php artisan test
```

Las pruebas utilizan **bd_chifa_centro** y transacciones que se revierten al terminar. No usan SQLite, RefreshDatabase ni truncamientos. No ejecutarlas en paralelo. Los contadores AUTO_INCREMENT pueden avanzar aunque las filas temporales se reviertan.

Laravel bloquea migrate:fresh, migrate:refresh, migrate:reset, migrate:rollback y db:wipe para esta base. Las migraciones de adaptación conservan datos; las modificaciones posteriores se realizan mediante nuevas migraciones correctivas.

## Integración con el celular y pendientes

Las rutas que necesita Flutter ya están implementadas y se verifican con un recorrido completo desde acceso y pedido hasta cocina, entrega, cobro y cierre. Falta verificar el funcionamiento con celulares Android reales simultáneos y una URL HTTPS accesible por ellos. La dirección 127.0.0.1 solo sirve en la PC; la app exige un certificado HTTPS aceptado por Android.

```powershell
flutter run --dart-define=USE_MOCK=false --dart-define=API_BASE_URL=https://tu-dominio.com/api/v1
```

Por indicación del proyecto, las contraseñas continúan temporalmente sin hash. No se muestran en los formularios ni en la API. Antes del uso real hay que incorporar hash y migrar las claves existentes. No se han agregado facturación electrónica, pasarela de pagos ni inventario por ingredientes.

La carta, usuarios y mesas se administran desde el panel. No es necesario volver a ejecutar el SQL original que elimina la base.

## Tiempo real y reglas de pedidos

Cocina y las vistas de pedidos de administración reciben eventos privados mediante Laravel Reverb y Laravel Echo. Los eventos se publican después de confirmar la transacción. Al recuperar la conexión, las pantallas vuelven a consultar el estado actual para recuperar cambios pendientes. El contador de demora se calcula en el navegador y no consulta la base cada segundo.

- Edición: durante los primeros 5 minutos de cada envío, en estado enviado o en preparación, antes de estar listo, entregado, anulado o cerrado. Una edición no reinicia su tiempo de espera.
- Adicionales: POST /orders con una nueva Idempotency-Key y solo los productos nuevos. Se crea un envío dentro de la misma atención abierta. Cocina muestra todos sus envíos juntos; los entregados quedan atenuados y los nuevos, destacados.
- Demora: borde y etiqueta amarillos desde 12 minutos, rojos desde 15 minutos, hasta entregar el envío. El contenido de los platos conserva su fondo y legibilidad.
- Sonido: pulsar «Activar sonido» en cada pantalla. Los avisos de pedidos nuevos, cambios y demoras tienen sonidos; se pueden silenciar. El navegador necesita esa interacción para habilitar audio.
- Cambios: se guarda el detalle anterior y nuevo. Cocina puede marcar la versión como vista. No puede avanzar con un formulario que corresponda a una versión anterior.
- La versión cambia cuando cambia el detalle, no al pasar entre estados de preparación.

Para arrancar desde una instalación nueva: instalar Composer y NPM, copiar/configurar .env, ejecutar php artisan key:generate si falta APP_KEY y php artisan reverb:install para generar las credenciales locales. No copiar claves privadas a código fuente.

Compilar el cliente después de instalar sus dependencias:

~~~powershell
npm ci
npm run build
~~~

Además del servidor web, mantener estos dos procesos activos en terminales independientes:

~~~powershell
php artisan reverb:start --host=127.0.0.1 --port=8080
php artisan queue:work --queue=avisos --sleep=1 --tries=5 --backoff=2 --timeout=30
~~~

La cola avisos se dedica a los eventos de pedidos. El canal restaurante.pedidos permite únicamente usuarios activos de Cocina o Administrador y utiliza sus sesiones web. Los mozos siguen utilizando la API REST con token.

En el entorno local, REVERB_HOST/PORT/SCHEME señalan el servidor interno. Para una instalación HTTPS, configurar también REVERB_PUBLIC_HOST, REVERB_PUBLIC_PORT y REVERB_PUBLIC_SCHEME para la dirección WSS visible desde el navegador, un certificado válido y el proxy correspondiente. REVERB_ALLOWED_ORIGINS permite los dominios de la web. No se configuró un dominio público ni un despliegue de producción.

### Editar desde la API

PUT /api/v1/orders/{id}, con Authorization: Bearer TOKEN e Idempotency-Key nueva para esa edición:

~~~json
{
  "expected_version": 1,
  "items": [
    {"product_id": "1", "quantity": 3, "notes": "Sin cebolla"}
  ]
}
~~~

items contiene el detalle completo que debe quedar. Se conservan los precios de los productos ya incluidos; los nuevos usan el precio del servidor. Laravel recalcula el stock y el total en una transacción. Las respuestas agregan attention_id, version, editable_until, can_edit, modified_at y kitchen_seen_version. Los campos anteriores permanecen compatibles.

La misma clave y contenido reintentan la misma edición incluso después del límite de cinco minutos. Otra carga con la misma clave devuelve 409; una versión antigua también devuelve 409 y exige actualizar. Un rechazo por plazo o estado devuelve 422. Un intento sin cambios no incrementa la versión ni produce una alerta nueva.

En cocina, POST /cocina/pedidos/{id}/visto recibe version. PATCH /cocina/pedidos/{id} recibe estado y expected_version. Estas operaciones son de la web y requieren el rol autorizado, no el token de un mozo.

### Estado para vincular Flutter

El backend conserva las APIs anteriores para acceso, mesas, carta, adicionales, entrega, anulación y cierre. Está preparado para comenzar la integración. La app actual todavía necesita una pantalla de edición, leer los nuevos campos y persistir id del pedido, versión esperada, cuerpo y clave de cada edición antes de enviarla. No se cambió su código Dart en esta etapa.

Después de adaptar Flutter, verificar el flujo en celulares Android reales simultáneos, con una URL HTTPS accesible desde ellos. Las pruebas automáticas y la comprobación local de Reverb no sustituyen esa prueba con dispositivos.

Pruebas de los temporizadores:

~~~powershell
node --test tests/js/alertas_pedidos.test.js
~~~
