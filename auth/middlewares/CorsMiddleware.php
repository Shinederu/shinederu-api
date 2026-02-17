<?php

class CorsMiddleware
{
    public static function apply()
    {
     
       
        // Récupère l'origine de la requête
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Autorise si domaine = shinederu.lol OU sous-domaine de shinederu.lol
        if (preg_match('#^https?://([a-z0-9-]+\.)*shinederu\.lol(:\d+)?$#i', $origin)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Vary: Origin'); // Pour le cache proxy/CDN
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Session-Id, X-Session-Id');
        header('Access-Control-Allow-Credentials: true');
        

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}


?>
