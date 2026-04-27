# Guia de implementacion de Casbin (paquete)

Guia general para proyectos que ya instalaron `sodeker/laravel-casbin` y quieren proteger su primer modulo (o siguientes) de forma consistente.

---

## 1) ¿Puedo tomar como ejemplo un modulo existente?

Si.  
Hay dos formas validas de implementar:

1. **Copiar el patron de un modulo existente** (por ejemplo `Roles` o `Users`).
2. **Seguir esta guia paso a paso** desde cero.

Si ambos se aplican correctamente, el resultado debe ser el mismo.

---

## 2) Checklist minimo antes de tocar un modulo

1. El paquete esta instalado y cargado.
2. `config/casbin.php` esta configurado (especialmente `enabled`, `connection`, `tenant_prefix`).
3. Existe contexto de tenant en sesion (si el proyecto es multi-tenant).
4. El middleware de tenant (si aplica) corre antes de tus rutas de modulo.
5. Las tablas de seguridad existen y estan pobladas:
   - `modules_tenant_apps`
   - `permissions`
   - `role_permissions`
   - `casbin_rule`
6. El proyecto no depende de interfaces/clases locales ajenas al paquete para autorizar (`App\Contracts\...`, `App\Casbin\...`) salvo que sea una extension intencional.

---

## 3) Paso a paso (modulo nuevo desde cero)

Escenario: modulo nuevo que aun no existe en `modules_tenant_apps`.

### Paso 1. Definir el modulo

Define:
- `module_code` (ejemplo: `products`)
- `module_name` (ejemplo: `Productos`)
- Acciones del modulo (ejemplo: `view`, `create`, `edit`, `delete`)

Regla: el `module_code` debe coincidir en rutas, permisos y sincronizacion.

### Paso 2. Registrar el modulo en catalogo

Inserta el modulo en `modules_tenant_apps` (por seeder o SQL).

Campos minimos recomendados:
- `app_id`
- `code`
- `name`
- `status`

### Paso 3. Registrar acciones del modulo

Inserta acciones en `permissions` asociadas al `module_id`.

Ejemplo de acciones:
- `view`
- `create`
- `edit`
- `delete`

### Paso 4. Asignar permisos a roles

Inserta/actualiza `role_permissions` para definir que rol puede ejecutar cada accion del modulo.

### Paso 5. Sincronizar politicas Casbin

Regenera o sincroniza `casbin_rule`:
- Politicas `p` por rol/dominio/modulo/accion.
- Grouping `g` por usuario/rol/dominio.

Si usas seeders de sincronizacion, ejecútalos despues de cambiar roles/permisos.

### Paso 6. Proteger rutas backend

Aplica middleware del paquete en cada endpoint:

- `casbin:products,view`
- `casbin:products,create`
- `casbin:products,edit`
- `casbin:products,delete`

Esto garantiza seguridad por backend aunque el frontend se manipule.

### Paso 7. Exponer permisos al frontend

En controlador, inyecta:

```php
use Sodeker\LaravelCasbin\Domain\Contracts\PermissionServiceInterface;
```

Y construye un objeto de permisos para UI:

```php
$permissions = [
    'view' => $permissionService->can($userId, $tenantId, 'products', 'view'),
    'create' => $permissionService->can($userId, $tenantId, 'products', 'create'),
    'edit' => $permissionService->can($userId, $tenantId, 'products', 'edit'),
    'delete' => $permissionService->can($userId, $tenantId, 'products', 'delete'),
];
```

Con esto ocultas botones y acciones en interfaz, pero la validacion real sigue en backend.

### Paso 8. Validar conexion de datos (multi-tenant)

Define claramente que tablas viven en base central y cuales en base tenant.

Regla clave:
- Si un modelo vive en base central, no lo apuntes a conexion tenant.
- Si un modelo vive por tenant, aseguralo con la conexion tenant.

Si mezclas esto, tendras errores tipo `relation "... does not exist"`.

### Paso 9. Probar end-to-end

Prueba minima:
1. Usuario con permisos completos.
2. Usuario sin permisos de alguna accion.
3. Acceso directo por URL a rutas protegidas.
4. Cambio de tenant (si aplica).
5. Confirmar:
   - Backend responde `403` cuando corresponde.
   - Frontend muestra/oculta acciones segun permisos.

---

## 4) Matriz conceptual de tablas

- `modules_tenant_apps`: catalogo de modulos por app.
- `permissions`: acciones por modulo.
- `role_permissions`: matriz rol-permiso.
- `tenant_users`: usuarios asociados a tenant (si aplica).
- `tenant_apps`: apps habilitadas por tenant (si aplica).
- `casbin_rule`: politicas (`p`) y agrupaciones (`g`).

---

## 5) Errores comunes y causa real

1. **Siempre 403**
   - `casbin_rule` desactualizada.
   - `module_code` no coincide entre ruta y permiso.

2. **No aparece el modulo**
   - Falta en `modules_tenant_apps` o esta inactivo.
   - Tenant no tiene app/modulo habilitado.

3. **UI permite, backend bloquea**
   - Frontend usa permisos incorrectos o desfasados.
   - Middleware backend si esta correcto (esto es bueno).

4. **`relation "... does not exist"`**
   - Modelo consultando conexion de BD incorrecta.

5. **Dependencias que no existen**
   - El modulo sigue usando contratos/clases locales antiguas en lugar del paquete.

---

## 6) Flujo rapido: “instale el paquete, que ejecuto ahora”

1. Crear modulo en `modules_tenant_apps`.
2. Crear acciones en `permissions`.
3. Asignar acciones a roles en `role_permissions`.
4. Sincronizar `casbin_rule`.
5. Proteger rutas con `casbin:<module>,<action>`.
6. Exponer permisos UI con `PermissionServiceInterface->can(...)`.
7. Probar con usuarios/roles/tenants reales.

---

## 7) Recomendacion para equipos

Estandariza una plantilla por modulo nuevo:
- rutas protegidas (`casbin:*`)
- helper/metodo para construir permisos UI
- tests de acceso permitido/denegado

Asi cualquier desarrollador puede:
- partir de un modulo existente, o
- seguir esta guia desde cero,

y llegar al mismo resultado funcional.
