<?php

class TokenService
{
    /**
     * Génère un token sécurisé.
     */
    public static function generateToken(int $length = 64): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    // (plus tard, tu peux ajouter des fonctions pour valider, blacklister, etc.)
}

?>