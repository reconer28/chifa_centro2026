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
- Cocina: pendientes, preparación, listos, entregados y anulados. Consulta novedades cada cinco segundos mientras la pestaña está visible.
- Pedidos y cobros: detalle con observaciones, precios históricos, registro de pago e impresión del resumen.
- Reportes: ventas entregadas por fecha de Lima, mozo y producto; platos vendidos y cobros de esas ventas. El reporte no cuenta pedidos anulados ni platos todavía en preparación.
- App: asignación con clave de un administrador activo, creación de pedidos y adicionales, entrega, anulación autorizada y cierre de atención.

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
