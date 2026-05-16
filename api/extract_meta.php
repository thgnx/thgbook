<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$result = ['success' => true, 'title' => '', 'author' => '', 'description' => '', 'genre' => ''];

if (empty($_FILES['book_file']) || $_FILES['book_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode($result);
    exit;
}

$tmpPath  = $_FILES['book_file']['tmp_name'];
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($tmpPath);

if ($mimeType === 'application/epub+zip') {
    $zip = new ZipArchive();
    if ($zip->open($tmpPath) === true) {
        // Locate the OPF rootfile via META-INF/container.xml
        $rootfilePath = 'OEBPS/content.opf';
        $containerXml = $zip->getFromName('META-INF/container.xml');
        if ($containerXml !== false) {
            libxml_use_internal_errors(true);
            $container = simplexml_load_string($containerXml);
            if ($container) {
                $nodes = $container->xpath('//*[local-name()="rootfile"]');
                if ($nodes && isset($nodes[0]['full-path'])) {
                    $rootfilePath = (string) $nodes[0]['full-path'];
                }
            }
        }

        // Read and parse the OPF file
        $opfContent = $zip->getFromName($rootfilePath);
        if ($opfContent === false) {
            $opfContent = $zip->getFromName('content.opf');
        }

        if ($opfContent !== false) {
            libxml_use_internal_errors(true);
            $opf = simplexml_load_string($opfContent);
            if ($opf) {
                $dcMap = [
                    'title'       => 'title',
                    'author'      => 'creator',
                    'description' => 'description',
                    'genre'       => 'subject',
                ];
                foreach ($dcMap as $key => $dcTag) {
                    $nodes = $opf->xpath('//*[local-name()="' . $dcTag . '"]');
                    if ($nodes) {
                        $text = trim((string) $nodes[0]);
                        if ($key === 'description') {
                            $text = trim(strip_tags($text));
                        }
                        $result[$key] = $text;
                    }
                }
            }
        }
        $zip->close();
    }
}
// PDF metadata is extracted client-side via PDF.js — nothing to do here.

echo json_encode($result);
