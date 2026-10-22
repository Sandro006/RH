<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

final class WebController extends BaseController
{
    public function asset(string $path = ''): ResponseInterface
    {
        $root = realpath(APPPATH . '../web');
        $uriPath = $this->request->getUri()->getPath();
        $marker = strpos($uriPath, '/web/');
        $relative = $marker === false ? $path : substr($uriPath, $marker + 5);
        $file = $root === false ? false : realpath($root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, urldecode($relative)));

        if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return $this->response->setStatusCode(404)->setBody('Fichier introuvable');
        }

        $mime = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'html' => 'text/html',
            'json' => 'application/json',
            'svg' => 'image/svg+xml',
            default => mime_content_type($file) ?: 'application/octet-stream',
        };

        return $this->response
            ->setContentType($mime)
            ->setBody((string) file_get_contents($file));
    }
}
