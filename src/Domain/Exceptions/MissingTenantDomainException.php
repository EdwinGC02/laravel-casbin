<?php

namespace Sodeker\LaravelCasbin\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Se intentó escribir una política sin tenant.
 *
 * Existe para que sea imposible, y no solo improbable, escribir en
 * `casbin_rule` una política sin dominio o borrar políticas de todos los
 * dominios a la vez sin pedirlo explícitamente. Una política sin dominio no
 * autoriza a nadie (el matcher exige `r.dom == p.dom`), así que escribirla es
 * siempre un error de programación; y un borrado sin dominio es la fuga que
 * permite que editar un rol en un tenant afecte a los demás.
 */
final class MissingTenantDomainException extends InvalidArgumentException
{
    public static function forWrite(): self
    {
        return new self('No se puede escribir una política de Casbin sin tenant: toda política pertenece a un dominio.');
    }
}
