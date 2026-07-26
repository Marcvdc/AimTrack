<?php

namespace App\Services\Vereniging;

use RuntimeException;

class VerenigingException extends RuntimeException
{
    public static function geenLid(): self
    {
        return new self('Deze gebruiker is geen lid van de vereniging.');
    }

    public static function gebruikerNietGevonden(string $email): self
    {
        return new self("Er is geen gebruiker met het e-mailadres {$email}.");
    }

    public static function alLid(): self
    {
        return new self('Deze gebruiker is al lid van de vereniging.');
    }

    public static function laatsteBeheerder(): self
    {
        return new self('De laatste beheerder kan niet verwijderd of gedegradeerd worden.');
    }
}
