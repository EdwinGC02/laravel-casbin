# Desarrollo del paquete

Cómo modificar `sodeker/laravel-casbin` y **comprobarlo dentro de una aplicación real sin publicar ninguna versión**, y cómo publicarla cuando ya está validada.

Este documento es para quien desarrolla el paquete. Lo que va dirigido a quien lo consume está en [`README.md`](README.md) y en [`IMPLEMENTATION.md`](IMPLEMENTATION.md).

El ejemplo es **Suite**, que hoy es la única app que lo usa. El procedimiento es idéntico en cualquier otra.

---

## 1. Por qué no se publica para probar

Composer instala las dependencias en `vendor/`, y ese directorio es territorio suyo: lo borra y lo reconstruye cuando quiere.

| Camino | Qué pasa |
|---|---|
| **Publicar un tag para probar** | Cada iteración cuesta un commit, un tag y una descarga, y deja en el historial versiones que nunca se validaron. Un historial de versiones es justo lo que no debe contener eso. |
| **Editar dentro de `vendor/`** | Funciona hasta que alguien ejecuta `composer install` y el cambio desaparece sin dejar rastro. Cuesta horas de depuración porque el código «se revirtió solo». |
| **Enlazar la carpeta de trabajo** | `vendor/sodeker/laravel-casbin` deja de ser una copia y pasa a ser un enlace a tu carpeta. Editas en un sitio, el proyecto lo ve al instante. Es lo que hace un *path repository*. |

---

## 2. Elegir el alcance de la prueba

No todos los métodos prueban lo mismo. Empieza por el más barato que responda a tu pregunta.

| Método | Qué prueba | Qué no prueba | Coste |
|---|---|---|---|
| Tests de la app consumidora | La lógica del paquete contra un esquema real | El circuito por navegador | Segundos |
| Path repository | Todo: formulario, guardado, políticas escritas, menú | El proceso de publicación | Dos ajustes, una vez |
| Tag de prerelease (`v1.2.0-beta.1`) | La instalación tal como será | — | Un tag y una descarga por iteración |

En la práctica: valida la lógica con los tests, monta el path repository para ver el circuito completo, y reserva la prerelease para la verificación final si el cambio es delicado.

---

## 3. Copia descargada frente a enlace vivo

Es la única idea que hay que entender; lo demás son dos ajustes que se derivan de ella.

| | Modo producción | Modo desarrollo |
|---|---|---|
| Qué hay en `vendor/sodeker/laravel-casbin` | Una copia de la versión publicada | Un enlace a tu carpeta de trabajo |
| Para que la app vea un cambio | commit → tag → push → `composer update` | Guardar el archivo |

En los dos modos la app importa `Sodeker\LaravelCasbin\…` igual: su código no cambia ni una línea. Lo único que cambia es a qué apunta `vendor/`.

**No conviven: es sustitución.** Al instalar desde el path, Composer borra la copia y pone el enlace en su lugar. No hay dos versiones activas ni ambigüedad sobre cuál se ejecuta.

---

## 4. Montaje en Suite

Suite está en `~/Desktop/projects-local/Suite` y el paquete en `~/Desktop/projects-local/laravel-casbin`: carpetas hermanas.

### 4.1 Declarar el repositorio local

```json
// Suite/composer.json — el path va PRIMERO
"repositories": [
    { "type": "path", "url": "../laravel-casbin", "options": { "symlink": true } },
    { "type": "vcs",  "url": "git@github.com:EdwinGC02/laravel-casbin.git" }
]
```

Composer busca en orden. Si `../laravel-casbin` existe, gana; si no —como en el servidor— lo ignora en silencio y usa GitHub. Por eso los dos bloques pueden convivir.

### 4.2 Abrir la ventana al contenedor

El contenedor `php` de Suite solo monta la carpeta del proyecto (`./:/var/www`), así que el paquete, que está fuera, no existiría dentro:

```yaml
# Suite/docker-compose.yml
php:
  volumes:
    - ./:/var/www
    - ../laravel-casbin:/var/laravel-casbin
```

La ruta de destino **no es arbitraria**, se calcula. Ver 4.5.

### 4.3 Recrear el contenedor

```bash
docker compose up -d --force-recreate php
```

Los volúmenes se fijan al crear el contenedor: reiniciarlo no basta.

### 4.4 Instalar desde el path y limpiar cachés

```bash
docker compose exec php composer require sodeker/laravel-casbin:@dev
docker compose exec php php artisan optimize:clear
```

`@dev` es obligatorio: la restricción de Suite es `^1.0` y el paquete local resuelve como `dev-main` —la rama, no un tag—, que no encaja en ese rango.

Sin `optimize:clear` seguirías leyendo la configuración cacheada y parecería que nada funcionó.

### 4.5 Por qué `/var/laravel-casbin`

Composer no crea un enlace absoluto, crea uno **relativo**: `../../../laravel-casbin`, tres niveles hacia arriba contados desde la carpeta que contiene el enlace, que es `vendor/sodeker/`.

```
vendor/sodeker → .. vendor → ../.. raíz del proyecto → ../../.. padre de la raíz
```

- En el Mac: `…/Suite/vendor/sodeker/` — 3 niveles → `…/projects-local/laravel-casbin`
- En el contenedor: `/var/www/vendor/sodeker/` — 3 niveles → `/var/laravel-casbin`

La raíz dentro del contenedor es `/var/www`, así que su padre es `/var`, no la raíz del sistema. Montarlo en `/laravel-casbin` parece razonable y es incorrecto: el enlace buscaría en `/var/laravel-casbin`, que no existiría, y Composer falla con un enlace roto cuyo mensaje no señala la causa.

---

## 5. Verificar que es un enlace y que está vivo

Es la comprobación que no conviene saltarse: si es una copia, estarías probando código viejo sin saberlo.

```bash
docker exec laravel_app_suite ls -la /var/www/vendor/sodeker/
# lrwxr-xr-x  laravel-casbin -> ../../../laravel-casbin/
# La flecha es la señal. Una carpeta normal (drwxr-xr-x) es una copia.

docker exec laravel_app_suite readlink -f /var/www/vendor/sodeker/laravel-casbin
# /var/laravel-casbin
```

Y que refleje los cambios, que es otra cosa:

```bash
echo "// centinela" >> laravel-casbin/README.md
docker exec laravel_app_suite tail -1 /var/www/vendor/sodeker/laravel-casbin/README.md
# // centinela   ← retira el centinela después
```

---

## 6. Probar sin montar nada

Cuando la duda es puntual, un `tinker` contra la app consumidora responde sin tocar `vendor/`, siempre que el código nuevo ya esté donde la app lo lee:

```bash
docker compose exec php php artisan tinker --execute="
  \$w = app(\Sodeker\LaravelCasbin\Domain\Contracts\TenantRolePolicyWriterInterface::class);
  echo count(\$w->policiesForRole('CONTADOR', 1)).PHP_EOL;
"
```

Y el comportamiento de fondo —que escribir en un tenant no toque a otro— se prueba mejor con un test de la app consumidora que con clics. En Suite es `tests/Feature/RolePermissionsTenantIsolationTest.php`.

**Prueba las dos mitades.** Que lo nuevo funcione es la mitad fácil; comprueba también que lo que debe negarse se niega (escribir sin tenant, borrar solo el dominio indicado). Una prueba que solo mira el camino feliz no detecta que alguien desactivó una guarda sin querer.

---

## 7. El ciclo de trabajo con el enlace puesto

| Qué cambiaste | Qué hay que hacer |
|---|---|
| El cuerpo de un método | Nada, se ve al instante |
| Una clase nueva | `composer dump-autoload` |
| `config/casbin.php` del paquete | `php artisan config:clear`, y copiar las claves nuevas a mano si la app publicó su propia config |
| Una plantilla de `casbin/` | Nada en la app: las plantillas solo se publican si no existen |
| El `composer.json` del paquete | `composer update sodeker/laravel-casbin` |

> El provider fusiona la configuración **solo en el primer nivel**: las claves nuevas que añadas no llegan solas a una app que ya publicó `config/casbin.php`.

---

## 8. Volver a modo producción

Es el paso que más se olvida y el único que puede romper un despliegue.

**El `composer.lock` de la app no se commitea mientras el paquete venga del path.** El lock registra la dependencia como `"type": "path"` con `"symlink": true`, y en el servidor `composer install` obedece al lock al pie de la letra: intentará enlazar a una carpeta que solo existe en tu máquina.

| Archivo de la app | ¿Se commitea? | Por qué |
|---|---|---|
| `composer.lock` | **No**, mientras apunte al path | Rompe el despliegue |
| `composer.json` | Se puede | Composer ignora el path si la carpeta no existe |
| `docker-compose.yml` | Se puede | Un volumen a una carpeta ausente no rompe nada |

### Secuencia de cierre

```bash
# 1) En el paquete: publicar la versión
git commit -am "…"
git tag v1.2.0
git push origin main --tags
```

```bash
# 2) En la app: quitar el bloque "path" de repositories y reinstalar desde GitHub
docker compose exec php composer require sodeker/laravel-casbin:^1.2
```

Eso borra el enlace, descarga la versión publicada y regenera un lock limpio, que ya sí se commitea.

Un tag publicado no se reescribe: revisa antes que los tests de la app consumidora estén verdes y que el `CHANGELOG.md` describa el cambio.

---

## 9. Versionado

SemVer, con el criterio de qué le rompe a quien consume:

| Cambio | Versión |
|---|---|
| Añadir un contrato, un método o una clave de configuración con valor por defecto | MENOR |
| Corregir un comportamiento sin cambiar firmas | PARCHE |
| Cambiar la firma de un contrato, quitar un método, cambiar un valor por defecto que altere el comportamiento | MAYOR |

Las **plantillas de `casbin/` no son API**: cambiarlas no rompe a nadie, porque solo se publican si no existen. Dilo en el changelog de todos modos, con la nota de que las copias ya publicadas hay que actualizarlas a mano.

Cada versión se documenta en [`CHANGELOG.md`](CHANGELOG.md): qué se corrigió, qué se añadió, qué cambió y —lo más importante— qué trabajo le queda a la app que actualiza.

---

## 10. Tropiezos conocidos

| Qué ves | Qué pasó | Qué hacer |
|---|---|---|
| `Permission denied (publickey)` | El contenedor no puede autenticarse con GitHub; casi siempre el agente de credenciales está vacío | `ssh-add --apple-use-keychain ~/.ssh/id_ed25519` y comprobar con `ssh-add -l` **dentro** del contenedor |
| Enlace roto en `vendor/` | La ruta del montaje no coincide con la que calcula el enlace relativo | Revisar 4.5: el destino es el padre de la raíz del proyecto dentro del contenedor |
| Los cambios no aparecen | O es una copia y no un enlace, o hay configuración cacheada | `ls -la vendor/sodeker/` para ver la flecha; después `php artisan optimize:clear` |
| `Class "…\TenantRolePolicyWriter" not found` | Clase nueva sin regenerar el autoload | `composer dump-autoload` |
| Funciona el enlace pero no el comportamiento | La app publicó su `config/casbin.php` y le faltan las claves nuevas | Copiarlas a mano |

Si algo falla al instalar desde el repositorio privado, el primer comando es siempre `ssh-add -l`: buena parte de esos fallos se reducen a que la credencial no está disponible, y el mensaje de error rara vez lo dice.
