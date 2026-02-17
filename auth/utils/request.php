<?php

function getSessionId(): ?string
{
    // Cookies (new and legacy)
    if (!empty($_COOKIE['sid'])) {
        return trim((string)$_COOKIE['sid']);
    }
    if (!empty($_COOKIE['session_id'])) { // legacy
        return trim((string)$_COOKIE['session_id']);
    }

    // Headers (normalized by PHP into $_SERVER)
    if (!empty($_SERVER['HTTP_X_SESSION_ID'])) {
        return trim((string)$_SERVER['HTTP_X_SESSION_ID']);
    }
    if (!empty($_SERVER['HTTP_SESSION_ID'])) { // legacy header
        return trim((string)$_SERVER['HTTP_SESSION_ID']);
    }

    return null;
}

?>

