<?php
/**
 * Traitement des images envoyees depuis l'admin.
 *
 * Objectif : accepter tous les formats photo courants — y compris le HEIC/HEIF
 * des iPhone et l'AVIF — et convertir en JPEG ce que les navigateurs ne savent
 * pas afficher, plutot que de rejeter le fichier en silence.
 *
 * Les moyens de conversion disponibles varient d'un hebergement a l'autre :
 * on essaie Imagick, puis la commande ImageMagick, puis GD. La premiere qui
 * aboutit gagne. Si aucune ne fonctionne, la fonction renvoie une erreur
 * explicite au lieu d'ignorer la photo.
 */

/** Formats affichables tels quels par tous les navigateurs. */
const IMG_FORMATS_NATIFS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

/** Formats acceptes a l'envoi mais convertis en JPEG avant stockage. */
const IMG_FORMATS_A_CONVERTIR = ['heic', 'heif', 'avif', 'bmp', 'tif', 'tiff'];

/**
 * Traite un fichier envoye et le range dans UPLOADS_DIR.
 *
 * @param array $file Une entree de $_FILES (tmp_name, name, error, size).
 * @return array{name: ?string, error: ?string} Nom du fichier stocke, ou message d'erreur.
 */
function traiterImageUpload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['size'])) {
        return ['name' => null, 'error' => messageErreurUpload($file['error'] ?? UPLOAD_ERR_NO_FILE)];
    }

    $tmp      = $file['tmp_name'];
    $original = $file['name'] ?? 'image';
    $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));

    // Le type reel prime sur l'extension, qu'un utilisateur peut renommer.
    $mime = function_exists('mime_content_type') ? (mime_content_type($tmp) ?: '') : '';
    if (str_starts_with($mime, 'image/')) {
        $extDuMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
                      'image/gif'  => 'gif', 'image/heic' => 'heic', 'image/heif' => 'heif',
                      'image/avif' => 'avif', 'image/bmp' => 'bmp', 'image/tiff' => 'tiff'];
        $ext = $extDuMime[$mime] ?? $ext;
    } elseif ($mime !== '') {
        // Un type non-image : ce n'est pas une photo, on refuse.
        return ['name' => null, 'error' => sprintf(
            'Le fichier « %s » n\'est pas une image (type détecté : %s).',
            htmlspecialchars($original), htmlspecialchars($mime))];
    }

    // Format directement affichable : on le range tel quel.
    if (in_array($ext, IMG_FORMATS_NATIFS, true)) {
        if (@getimagesize($tmp) === false && $ext !== 'webp') {
            // getimagesize ne lit pas toujours le WebP selon la version de PHP :
            // on ne bloque que les autres formats reellement illisibles.
            return ['name' => null, 'error' => sprintf(
                'Le fichier « %s » semble corrompu ou n\'est pas une image valide.',
                htmlspecialchars($original))];
        }
        $nom = uniqid('img_', true) . '.' . $ext;
        if (!move_uploaded_file($tmp, UPLOADS_DIR . $nom)) {
            return ['name' => null, 'error' => sprintf(
                'Impossible d\'enregistrer « %s » sur le serveur.', htmlspecialchars($original))];
        }
        return ['name' => $nom, 'error' => null];
    }

    // Format non affichable par les navigateurs (HEIC iPhone, AVIF, TIFF…) :
    // on le convertit en JPEG.
    if (in_array($ext, IMG_FORMATS_A_CONVERTIR, true)) {
        $nom  = uniqid('img_', true) . '.jpg';
        $dest = UPLOADS_DIR . $nom;
        if (convertirEnJpeg($tmp, $dest)) {
            return ['name' => $nom, 'error' => null];
        }
        return ['name' => null, 'error' => sprintf(
            'Le format %s de « %s » n\'a pas pu être converti par le serveur. '
            . 'Enregistrez la photo en JPG avant de l\'envoyer '
            . '(sur iPhone : Réglages → Appareil photo → Formats → Haute compatibilité).',
            strtoupper($ext), htmlspecialchars($original))];
    }

    return ['name' => null, 'error' => sprintf(
        'Format non reconnu pour « %s ». Formats acceptés : JPG, PNG, WebP, GIF, HEIC, AVIF, BMP, TIFF.',
        htmlspecialchars($original))];
}

/**
 * Convertit une image en JPEG en essayant les moyens disponibles, du plus
 * capable au plus limite. GD est tente en dernier : il ne lit ni HEIC ni AVIF.
 */
function convertirEnJpeg(string $source, string $destination): bool
{
    // 1. Imagick — la solution la plus complete quand l'hebergeur la propose.
    if (class_exists('Imagick')) {
        try {
            $im = new Imagick();
            $im->readImage($source);
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality(88);
            // Une photo HEIC peut contenir plusieurs images (Live Photo) :
            // on ne garde que la premiere.
            $im = $im->flattenImages();
            $ok = $im->writeImage($destination);
            $im->clear();
            if ($ok && filesize($destination) > 0) return true;
        } catch (Throwable $e) {
            error_log('Conversion Imagick impossible — ' . $e->getMessage());
        }
    }

    // 2. ImageMagick en ligne de commande, si l'hebergeur autorise exec().
    if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
        foreach (['magick', 'convert'] as $binaire) {
            $chemin = trim((string)@shell_exec('command -v ' . escapeshellarg($binaire) . ' 2>/dev/null'));
            if ($chemin === '') continue;
            $cmd = escapeshellarg($chemin) . ' ' . escapeshellarg($source . '[0]')
                 . ' -quality 88 ' . escapeshellarg($destination) . ' 2>/dev/null';
            @exec($cmd, $sortie, $code);
            if ($code === 0 && is_file($destination) && filesize($destination) > 0) return true;
        }
    }

    // 3. GD — ne gere ni HEIC ni AVIF, mais sauve les BMP et certains TIFF.
    if (function_exists('imagecreatefromstring')) {
        $donnees = @file_get_contents($source);
        if ($donnees !== false) {
            $img = @imagecreatefromstring($donnees);
            if ($img !== false) {
                $ok = @imagejpeg($img, $destination, 88);
                imagedestroy($img);
                if ($ok && is_file($destination) && filesize($destination) > 0) return true;
            }
        }
    }

    return false;
}

/**
 * Met en forme les refus d'upload pour l'admin.
 * Renvoie une chaine vide si toutes les photos sont passees.
 */
function messagesUpload(array $erreurs): string
{
    if (!$erreurs) {
        return '';
    }
    $html = '<div class="alert alert-error"><strong>'
          . count($erreurs) . ' photo(s) non enregistrée(s) :</strong><ul style="margin:8px 0 0 18px;">';
    foreach ($erreurs as $e) {
        $html .= '<li>' . $e . '</li>';
    }
    return $html . '</ul></div>';
}

/** Traduit un code d'erreur d'upload PHP en message comprehensible. */
function messageErreurUpload(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Photo trop lourde : la limite du serveur est de '
                 . (ini_get('upload_max_filesize') ?: '?') . '.';
        case UPLOAD_ERR_PARTIAL:
            return 'L\'envoi de la photo a été interrompu. Réessayez.';
        case UPLOAD_ERR_NO_FILE:
            return 'Aucun fichier reçu.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            return 'Le serveur n\'a pas pu écrire le fichier temporaire.';
        case UPLOAD_ERR_EXTENSION:
            return 'L\'envoi a été bloqué par une extension du serveur.';
        default:
            return 'Erreur inconnue lors de l\'envoi de la photo.';
    }
}
