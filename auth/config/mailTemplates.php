<?php
// /config/mail_templates.php
return [

  // 1) Après inscription — email de vérification + lien d’annulation
  'verify_email_register' => [
    'subject' => 'Vérification de votre compte',
    'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
  <head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>
  <body style="margin:0;padding:0;background:#f5f7fb;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;">
      <tr><td align="center" style="padding:24px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e9ecf3;">
          <tr>
            <td style="padding:24px 24px 0 24px;background:#0f172a;color:#ffffff;">
              <h1 style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:20px;line-height:1.3;">Bienvenue ✨</h1>
              <p style="margin:8px 0 0 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;opacity:.9;">Merci pour votre inscription.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:24px;">
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:16px;color:#0f172a;margin:0 0 16px 0;">
                Pour vérifier votre adresse e-mail, cliquez sur le bouton ci-dessous :
              </p>
              <p style="margin:0 0 24px 0;" align="center">
                <a href="{{verify_link}}" style="display:inline-block;font-family:Arial,Helvetica,sans-serif;font-size:16px;text-decoration:none;padding:12px 20px;border-radius:8px;background:#2563eb;color:#ffffff;">Vérifier mon e-mail</a>
              </p>
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#334155;margin:0 0 8px 0;">
                Ou copiez ce lien dans votre navigateur :
              </p>
              <p style="word-break:break-all;font-family:Consolas,Menlo,monospace;font-size:12px;color:#1f2937;margin:0 0 24px 0;">{{verify_link}}</p>

              <hr style="border:none;border-top:1px solid #e9ecf3;margin:16px 0 16px 0;">
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#0f172a;margin:0 0 8px 0;">
                Si vous n’êtes pas à l’origine de cette inscription, vous pouvez l’annuler ici :
              </p>
              <p style="margin:0;" align="center">
                <a href="{{revoke_link}}" style="display:inline-block;font-family:Arial,Helvetica,sans-serif;font-size:14px;text-decoration:none;padding:10px 16px;border-radius:8px;background:#ef4444;color:#ffffff;">Annuler l’inscription</a>
              </p>
              <p style="word-break:break-all;font-family:Consolas,Menlo,monospace;font-size:12px;color:#6b7280;margin:12px 0 0 0;">{{revoke_link}}</p>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 24px;background:#f8fafc;">
              <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;">
                Cet e-mail a été envoyé automatiquement. Merci de ne pas y répondre.
              </p>
            </td>
          </tr>
        </table>
      </td></tr>
    </table>
  </body>
</html>
HTML,
    'text' => <<<TEXT
Bienvenue !

Merci pour votre inscription.

Vérifiez votre adresse e-mail en ouvrant ce lien :
{{verify_link}}

Si vous n'êtes pas à l'origine de cette inscription, annulez ici :
{{revoke_link}}

(Cet e-mail a été envoyé automatiquement, ne pas répondre.)
TEXT,
  ],

  // 2) Connexion refusée car email non vérifié — renvoi du mail de vérif
  'verify_email_reminder' => [
    'subject' => 'Vérification de votre compte',
    'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
  <head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>
  <body style="margin:0;padding:0;background:#f5f7fb;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;">
      <tr><td align="center" style="padding:24px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e9ecf3;">
          <tr>
            <td style="padding:24px;background:#0f172a;color:#ffffff;">
              <h1 style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:20px;">Vérifiez votre e-mail pour vous connecter</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:24px;">
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:16px;color:#0f172a;margin:0 0 16px 0;">
                Pour continuer, confirmez votre adresse e-mail :
              </p>
              <p align="center" style="margin:0 0 24px 0;">
                <a href="{{verify_link}}" style="display:inline-block;font-family:Arial,Helvetica,sans-serif;font-size:16px;text-decoration:none;padding:12px 20px;border-radius:8px;background:#2563eb;color:#ffffff;">Vérifier mon e-mail</a>
              </p>
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#334155;margin:0 0 8px 0;">Lien direct :</p>
              <p style="word-break:break-all;font-family:Consolas,Menlo,monospace;font-size:12px;color:#1f2937;margin:0 0 24px 0;">{{verify_link}}</p>

              <hr style="border:none;border-top:1px solid #e9ecf3;margin:16px 0 16px 0;">
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#0f172a;margin:0 0 8px 0;">
                Pas à l’origine de cette inscription ? Annulez :
              </p>
              <p style="margin:0;" align="center">
                <a href="{{revoke_link}}" style="display:inline-block;font-family:Arial,Helvetica,sans-serif;font-size:14px;text-decoration:none;padding:10px 16px;border-radius:8px;background:#ef4444;color:#ffffff;">Annuler</a>
              </p>
              <p style="word-break:break-all;font-family:Consolas,Menlo,monospace;font-size:12px;color:#6b7280;margin:12px 0 0 0;">{{revoke_link}}</p>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 24px;background:#f8fafc;">
              <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;">E-mail automatique, ne pas répondre.</p>
            </td>
          </tr>
        </table>
      </td></tr>
    </table>
  </body>
</html>
HTML,
    'text' => <<<TEXT
Bonjour,

Pour vous connecter, vérifiez d'abord votre adresse e-mail :
{{verify_link}}

Si vous n'êtes pas à l'origine de cette inscription, annulez ici :
{{revoke_link}}

E-mail automatique, ne pas répondre.
TEXT,
  ],

  // 3) Demande de réinitialisation de mot de passe
  'password_reset_request' => [
    'subject' => 'Modification de votre mot de passe',
    'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
  <head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>
  <body style="margin:0;padding:0;background:#f5f7fb;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;">
      <tr><td align="center" style="padding:24px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e9ecf3;">
          <tr>
            <td style="padding:24px;background:#0f172a;color:#ffffff;">
              <h1 style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:20px;">Réinitialisation du mot de passe</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:24px;">
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:16px;color:#0f172a;margin:0 0 16px 0;">
                Cliquez sur le bouton pour définir un nouveau mot de passe :
              </p>
              <p align="center" style="margin:0 0 24px 0;">
                <a href="{{reset_link}}" style="display:inline-block;font-family:Arial,Helvetica,sans-serif;font-size:16px;text-decoration:none;padding:12px 20px;border-radius:8px;background:#2563eb;color:#ffffff;">Modifier mon mot de passe</a>
              </p>
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#334155;margin:0 0 8px 0;">Lien direct :</p>
              <p style="word-break:break-all;font-family:Consolas,Menlo,monospace;font-size:12px;color:#1f2937;margin:0 0 16px 0;">{{reset_link}}</p>
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#64748b;margin:0;">
                Si vous n’êtes pas à l’origine de cette demande, vous pouvez ignorer cet e-mail.
              </p>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 24px;background:#f8fafc;">
              <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;">E-mail automatique, ne pas répondre.</p>
            </td>
          </tr>
        </table>
      </td></tr>
    </table>
  </body>
</html>
HTML,
    'text' => <<<TEXT
Bonjour,

Pour modifier votre mot de passe, ouvrez ce lien :
{{reset_link}}

Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.

E-mail automatique, ne pas répondre.
TEXT,
  ],

  // 4) Demande de mise à jour d’email — notification envoyée à l’ancienne adresse
  'email_update_notice_old' => [
    'subject' => 'Information de changement d’e-mail',
    'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
  <head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>
  <body style="margin:0;padding:0;background:#f5f7fb;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;">
      <tr><td align="center" style="padding:24px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e9ecf3;">
          <tr>
            <td style="padding:24px;background:#0f172a;color:#ffffff;">
              <h1 style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:20px;">Demande de changement d’e-mail</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:24px;">
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:16px;color:#0f172a;margin:0 0 12px 0;">
                Une demande de modification d’adresse e-mail vers
                <strong style="color:#0f172a;">{{new_email}}</strong> a été effectuée.
              </p>
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#0f172a;margin:0 0 8px 0;">
                Si vous n’êtes pas à l’origine de cette action, vous pouvez l’annuler ici :
              </p>
              <p style="margin:0;" align="center">
                <a href="{{revoke_link}}" style="display:inline-block;font-family:Arial,Helvetica,sans-serif;font-size:14px;text-decoration:none;padding:10px 16px;border-radius:8px;background:#ef4444;color:#ffffff;">Annuler la modification</a>
              </p>
              <p style="word-break:break-all;font-family:Consolas,Menlo,monospace;font-size:12px;color:#6b7280;margin:12px 0 0 0;">{{revoke_link}}</p>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 24px;background:#f8fafc;">
              <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;">E-mail automatique, ne pas répondre.</p>
            </td>
          </tr>
        </table>
      </td></tr>
    </table>
  </body>
</html>
HTML,
    'text' => <<<TEXT
Bonjour,

Une demande de modification d'adresse e-mail vers {{new_email}} a été effectuée.
Si vous n'êtes pas à l'origine de cette action, annulez ici :
{{revoke_link}}

E-mail automatique, ne pas répondre.
TEXT,
  ],

  // 5) Demande de mise à jour d’email — confirmation envoyée à la nouvelle adresse
  'email_update_confirm_new' => [
    'subject' => 'Confirmation de changement d’e-mail',
    'html' => <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
  <head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>
  <body style="margin:0;padding:0;background:#f5f7fb;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;">
      <tr><td align="center" style="padding:24px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e9ecf3;">
          <tr>
            <td style="padding:24px;background:#0f172a;color:#ffffff;">
              <h1 style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:20px;">Confirmez votre nouvelle adresse</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:24px;">
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:16px;color:#0f172a;margin:0 0 16px 0;">
                Pour finaliser le changement d’e-mail, cliquez ci-dessous :
              </p>
              <p align="center" style="margin:0 0 24px 0;">
                <a href="{{confirm_link}}" style="display:inline-block;font-family:Arial,Helvetica,sans-serif;font-size:16px;text-decoration:none;padding:12px 20px;border-radius:8px;background:#16a34a;color:#ffffff;">Confirmer mon e-mail</a>
              </p>
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#334155;margin:0 0 8px 0;">Lien direct :</p>
              <p style="word-break:break-all;font-family:Consolas,Menlo,monospace;font-size:12px;color:#1f2937;margin:0;">{{confirm_link}}</p>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 24px;background:#f8fafc;">
              <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#64748b;">E-mail automatique, ne pas répondre.</p>
            </td>
          </tr>
        </table>
      </td></tr>
    </table>
  </body>
</html>
HTML,
    'text' => <<<TEXT
Bonjour,

Pour confirmer votre nouvelle adresse e-mail, ouvrez ce lien :
{{confirm_link}}

E-mail automatique, ne pas répondre.
TEXT,
  ],

];
