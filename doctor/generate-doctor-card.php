<?php
/*
|--------------------------------------------------------------------------
| Doctor Photo Card Metadata Download
|--------------------------------------------------------------------------
| Save this file as:
| /doctor/generate-doctor-card.php
|
| It receives the browser-created JPG photo card and embeds Windows-friendly
| EXIF/XMP fields before returning the final downloadable JPG.
|--------------------------------------------------------------------------
*/

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

/*
|--------------------------------------------------------------------------
| Input Helpers
|--------------------------------------------------------------------------
*/

function doctor_card_clean_text($value, int $limit = 500): string
{
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
    $value = preg_replace('/\s+/u', ' ', (string)$value);

    if (function_exists('mb_substr')) {
        return mb_substr((string)$value, 0, $limit, 'UTF-8');
    }

    return substr((string)$value, 0, $limit);
}

function doctor_card_xml($value): string
{
    return htmlspecialchars(
        doctor_card_clean_text($value, 1200),
        ENT_XML1 | ENT_QUOTES,
        'UTF-8'
    );
}

function doctor_card_utf16le(string $value): string
{
    $value = doctor_card_clean_text($value, 1000);

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'UTF-16LE//IGNORE', $value);

        if ($converted !== false) {
            return $converted . "\x00\x00";
        }
    }

    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($value, 'UTF-16LE', 'UTF-8') . "\x00\x00";
    }

    return $value . "\x00";
}

function doctor_card_jpeg_segment(string $marker, string $payload): string
{
    if (strlen($marker) !== 2 || strlen($payload) > 65533) {
        return '';
    }

    return $marker . pack('n', strlen($payload) + 2) . $payload;
}

function doctor_card_build_exif(string $title, string $subject, string $tags, string $author, int $rating): string
{
    $title = doctor_card_clean_text($title, 220);
    $subject = doctor_card_clean_text($subject, 500);
    $tags = doctor_card_clean_text($tags, 700);
    $author = doctor_card_clean_text($author, 220);
    $copyright = '© ' . date('Y') . ' ' . ($author !== '' ? $author : 'MedicBD');

    $entries = [
        ['tag' => 0x010E, 'type' => 2, 'count' => strlen($title) + 1, 'data' => $title . "\x00"],
        ['tag' => 0x013B, 'type' => 2, 'count' => strlen($author) + 1, 'data' => $author . "\x00"],
        ['tag' => 0x4746, 'type' => 3, 'count' => 1, 'inline' => pack('v', $rating) . "\x00\x00"],
        ['tag' => 0x4749, 'type' => 3, 'count' => 1, 'inline' => pack('v', [0, 1, 25, 50, 75, 99][$rating] ?? 0) . "\x00\x00"],
        ['tag' => 0x8298, 'type' => 2, 'count' => strlen($copyright) + 1, 'data' => $copyright . "\x00"],
        ['tag' => 0x9C9B, 'type' => 1, 'count' => strlen(doctor_card_utf16le($title)), 'data' => doctor_card_utf16le($title)],
        ['tag' => 0x9C9C, 'type' => 1, 'count' => strlen(doctor_card_utf16le($subject)), 'data' => doctor_card_utf16le($subject)],
        ['tag' => 0x9C9D, 'type' => 1, 'count' => strlen(doctor_card_utf16le($author)), 'data' => doctor_card_utf16le($author)],
        ['tag' => 0x9C9E, 'type' => 1, 'count' => strlen(doctor_card_utf16le($tags)), 'data' => doctor_card_utf16le($tags)],
    ];

    usort($entries, static function (array $left, array $right): int {
        return $left['tag'] <=> $right['tag'];
    });

    $entryCount = count($entries);
    $dataOffset = 8 + 2 + ($entryCount * 12) + 4;
    $entryBinary = '';
    $dataBinary = '';

    foreach ($entries as $entry) {
        $entryBinary .= pack('v', $entry['tag']);
        $entryBinary .= pack('v', $entry['type']);
        $entryBinary .= pack('V', $entry['count']);

        if (isset($entry['inline'])) {
            $entryBinary .= $entry['inline'];
            continue;
        }

        $entryBinary .= pack('V', $dataOffset + strlen($dataBinary));
        $dataBinary .= $entry['data'];
    }

    $tiff = "II";
    $tiff .= pack('v', 42);
    $tiff .= pack('V', 8);
    $tiff .= pack('v', $entryCount);
    $tiff .= $entryBinary;
    $tiff .= pack('V', 0);
    $tiff .= $dataBinary;

    return doctor_card_jpeg_segment("\xFF\xE1", "Exif\x00\x00" . $tiff);
}

function doctor_card_build_xmp(string $title, string $subject, array $tags, string $author, int $rating): string
{
    $tagXml = '';

    foreach ($tags as $tag) {
        $tag = doctor_card_clean_text($tag, 120);

        if ($tag !== '') {
            $tagXml .= '<rdf:li>' . doctor_card_xml($tag) . '</rdf:li>';
        }
    }

    $xmp = '<?xpacket begin="' . "\xEF\xBB\xBF" . '" id="W5M0MpCehiHzreSzNTczkc9d"?>';
    $xmp .= '<x:xmpmeta xmlns:x="adobe:ns:meta/">';
    $xmp .= '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">';
    $xmp .= '<rdf:Description rdf:about=""';
    $xmp .= ' xmlns:dc="http://purl.org/dc/elements/1.1/"';
    $xmp .= ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"';
    $xmp .= ' xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/"';
    $xmp .= ' xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/">';
    $xmp .= '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . doctor_card_xml($title) . '</rdf:li></rdf:Alt></dc:title>';
    $xmp .= '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . doctor_card_xml($subject) . '</rdf:li></rdf:Alt></dc:description>';
    $xmp .= '<dc:subject><rdf:Bag>' . $tagXml . '</rdf:Bag></dc:subject>';
    $xmp .= '<dc:creator><rdf:Seq><rdf:li>' . doctor_card_xml($author) . '</rdf:li></rdf:Seq></dc:creator>';
    $xmp .= '<xmp:Rating>' . $rating . '</xmp:Rating>';
    $xmp .= '<photoshop:Credit>' . doctor_card_xml($author) . '</photoshop:Credit>';
    $xmp .= '<xmpRights:WebStatement>https://medic.bd</xmpRights:WebStatement>';
    $xmp .= '</rdf:Description></rdf:RDF></x:xmpmeta>';
    $xmp .= '<?xpacket end="w"?>';

    return doctor_card_jpeg_segment(
        "\xFF\xE1",
        "http://ns.adobe.com/xap/1.0/\x00" . $xmp
    );
}

function doctor_card_inject_metadata(string $jpg, string $title, string $subject, string $tags, string $author, int $rating): string
{
    if (substr($jpg, 0, 2) !== "\xFF\xD8") {
        return '';
    }

    $tagList = array_values(array_filter(array_map(
        static fn ($tag): string => doctor_card_clean_text($tag, 120),
        preg_split('/\s*,\s*/u', $tags) ?: []
    )));

    $exif = doctor_card_build_exif($title, $subject, $tags, $author, $rating);
    $xmp = doctor_card_build_xmp($title, $subject, $tagList, $author, $rating);

    if ($exif === '' || $xmp === '') {
        return '';
    }

    return substr($jpg, 0, 2) . $exif . $xmp . substr($jpg, 2);
}

function doctor_card_filename(string $value): string
{
    $value = doctor_card_clean_text($value, 170);
    $value = preg_replace('/\.jpe?g$/iu', '', $value);

    /*
     * Public photo-card URL format:
     * dr-ahmed-hasan-cardiology-dhaka.jpg
     */
    if (function_exists('iconv')) {
        $ascii_value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if ($ascii_value !== false) {
            $value = $ascii_value;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');

    return ($value !== '' ? $value : 'doctor-profile-card') . '.jpg';
}

function doctor_card_directory_by_lang(string $lang): string
{
    return strtolower(trim($lang)) === 'bn'
        ? 'uploads/bn-doctor-cards'
        : 'uploads/doctor-cards';
}

function doctor_card_public_url(string $filename, string $lang = 'en'): string
{
    $relativePath = doctor_card_directory_by_lang($lang) . '/' . rawurlencode($filename);

    if (function_exists('site_url')) {
        return site_url($relativePath);
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';

    return $host !== ''
        ? rtrim($scheme . '://' . $host, '/') . '/' . $relativePath
        : '/' . $relativePath;
}

function doctor_card_store_public_file(string $jpg, string $filename, string $lang = 'en'): bool
{
    /*
     * English:
     * https://medic.bd/uploads/doctor-cards/dr-ahmed-hasan-cardiology-dhaka.jpg
     *
     * Bangla:
     * https://medic.bd/uploads/bn-doctor-cards/dr-ahmed-hasan-cardiology-dhaka.jpg
     */
    $directory = dirname(__DIR__) . '/' . doctor_card_directory_by_lang($lang);

    if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
        return false;
    }

    if (!is_writable($directory)) {
        return false;
    }

    $filename = basename($filename);
    $target = $directory . DIRECTORY_SEPARATOR . $filename;
    $temporary = $target . '.' . uniqid('tmp-', true);

    if (@file_put_contents($temporary, $jpg, LOCK_EX) === false) {
        return false;
    }

    if (!@rename($temporary, $target)) {
        @unlink($temporary);
        return false;
    }

    @chmod($target, 0644);

    return true;
}

/*
|--------------------------------------------------------------------------
| Decode Card Image
|--------------------------------------------------------------------------
*/

$dataUri = trim((string)($_POST['card_image'] ?? ''));

if (!preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=\s]+)$#', $dataUri, $matches)) {
    http_response_code(422);
    exit('Invalid photo card image.');
}

$jpg = base64_decode(preg_replace('/\s+/', '', $matches[1]), true);

if ($jpg === false || strlen($jpg) < 1024 || strlen($jpg) > 8 * 1024 * 1024) {
    http_response_code(422);
    exit('Invalid photo card file size.');
}

if (substr($jpg, 0, 2) !== "\xFF\xD8") {
    http_response_code(422);
    exit('Invalid JPG image.');
}

$title = doctor_card_clean_text($_POST['title'] ?? '', 220);
$subject = doctor_card_clean_text($_POST['subject'] ?? '', 500);
$tags = doctor_card_clean_text($_POST['tags'] ?? '', 700);
$author = doctor_card_clean_text($_POST['author'] ?? 'MedicBD', 220);
$rating = max(0, min(5, (int)($_POST['rating'] ?? 0)));

if ($title === '') {
    $title = 'Doctor Profile';
}

if ($subject === '') {
    $subject = $title;
}

if ($author === '') {
    $author = 'MedicBD';
}

$finalJpg = doctor_card_inject_metadata(
    $jpg,
    $title,
    $subject,
    $tags,
    $author,
    $rating
);

if ($finalJpg === '') {
    http_response_code(500);
    exit('Unable to add image metadata.');
}

$action = strtolower(trim((string)($_POST['action'] ?? 'download')));

if (!in_array($action, ['save', 'download'], true)) {
    http_response_code(422);
    exit('Invalid photo card action.');
}

$lang = strtolower(trim((string)($_POST['lang'] ?? 'en')));

if (!in_array($lang, ['en', 'bn'], true)) {
    $lang = 'en';
}

$filename = doctor_card_filename(
    doctor_card_clean_text($_POST['filename'] ?? '', 170) !== ''
        ? (string)$_POST['filename']
        : $title
);

if (!doctor_card_store_public_file($finalJpg, $filename, $lang)) {
    http_response_code(500);
    exit('Unable to save the photo card. Please verify that uploads/doctor-cards or uploads/bn-doctor-cards is writable.');
}

$publicUrl = doctor_card_public_url($filename, $lang);

if ($action === 'save') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');

    echo json_encode([
        'success' => true,
        'url' => $publicUrl,
        'filename' => $filename,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

$asciiFilename = preg_replace('/[^\x20-\x7E]/', '', $filename);
$asciiFilename = trim((string)$asciiFilename);

if ($asciiFilename === '') {
    $asciiFilename = 'doctor-profile-card.jpg';
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . strlen($finalJpg));
header('Content-Disposition: attachment; filename="' . addcslashes($asciiFilename, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');

echo $finalJpg;
exit;
