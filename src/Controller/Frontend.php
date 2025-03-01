<?php

namespace Bolt\Extension\MichaelMezger\Api\Controller;

use Silex;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Bolt\Storage\Entity\Content;

class Frontend extends \Bolt\Controller\Frontend
{
    public function api(Request $request)
    {
        $data = $this->buildData($request);

        // Neues Content-Objekt erstellen
        $content = new Content();
        $content->setContenttype('entries');
        foreach ($data as $field => $value) {
            $content->set($field, $value);
        }

        // Speichern des neuen Eintrags
        $this->app['storage']->getRepository('entries')->save($content);

        // ID abrufen
        $id = $content->getId();
        echo "Neuer Eintrag erstellt mit ID: " . $id;
        die;
    }

    protected function buildData(Request $request)
    {
        $data = [];

        $simpleFields = [
            'title',
            'slug',
            'body',
            'teaser',
            'teaser_button',
            'info',
            'status'
        ];
        $imageFields = [
            'image',
            'image2',
            'image3'
        ];
        $defaults = [
            'status' => 'published'
        ];

        foreach ($simpleFields as $field ) {
            if (empty($request->get($field))) {
                if (isset($defaults[$field])) {
                    $data[$field] = $defaults[$field];
                }
                continue;
            }
            $data[$field] = $request->get($field);
        }

        foreach ($imageFields as $field) {
            if (!isset($_FILES[$field])) {
                continue;
            }

            // Datei-Stream öffnen
            $stream = fopen($_FILES[$field]['tmp_name'], 'r+');

            // Datei in Bolt speichern (z. B. in `public/files/uploads/`)
            $targetPath = 'uploads/' . md5(uniqid(rand(), true)) . '.jpg';
            $this->app['filesystem']->getFilesystem('files')->writeStream($targetPath, $stream);
            $data[$field] = ['file' => $targetPath];
        }

        return $data;
    }
}